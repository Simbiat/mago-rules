<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\Helpers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simbiat\MagoRules\Helpers\SqlDateTimeHelper;

/**
 * The parts of the helper, that work on a string only. The parts that need a syntax tree are tested through the rules, with fixtures.
 */
#[CoversClass(SqlDateTimeHelper::class)]
final class SqlDateTimeHelperTest extends TestCase
{
    /**
     * @return array<string, array{int, int}>
     */
    public static function precisionProvider(): array
    {
        return [
            'zero' => [0, 0],
            'inside the range' => [3, 3],
            'maximum' => [6, 6],
            'above the maximum' => [9, 6],
            'below the minimum' => [-3, 0],
        ];
    }

    #[DataProvider('precisionProvider')]
    public function testNormalisePrecision(int $precision, int $expected): void
    {
        self::assertSame($expected, SqlDateTimeHelper::normalisePrecision($precision));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function sqlProvider(): array
    {
        return [
            'select' => ['SELECT 1', true],
            'lower case' => ['select 1', true],
            'leading whitespace' => ["  \n\tSELECT 1", true],
            'with' => ['WITH x AS (SELECT 1) SELECT * FROM x', true],
            'call' => ['CALL p()', true],
            'a word that only starts like a keyword' => ['SELECTED things', false],
            'text' => ['Hello, NOW()', false],
            'a keyword in the middle' => ['Please SELECT 1', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('sqlProvider')]
    public function testLooksLikeSql(string $body, bool $expected): void
    {
        self::assertSame($expected, SqlDateTimeHelper::looksLikeSql($body));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function maskProvider(): array
    {
        return [
            'no comments' => ['SELECT NOW()', 'SELECT NOW()'],
            'line comment' => ["SELECT 1 -- NOW()\nFROM t", 'SELECT 1 '.\str_repeat(' ', 8)."\nFROM t"],
            'block comment' => ['SELECT /* NOW() */ 1', 'SELECT '.\str_repeat(' ', 11).' 1'],
            // The length is kept, but a line break inside of a comment is masked, too.
            'multi-line block comment' => ["SELECT /* a\nb */ 1", 'SELECT '.\str_repeat(' ', 9).' 1'],
            // The offsets are in bytes: a two-byte character is masked with two spaces.
            'multibyte character in a comment' => ['-- ы', '    '.' '],
        ];
    }

    #[DataProvider('maskProvider')]
    public function testMask(string $body, string $expected): void
    {
        $masked = SqlDateTimeHelper::mask($body);

        self::assertSame(\strlen($body), \strlen($masked));
        self::assertSame($expected, $masked);
    }

    public function testFindUtc(): void
    {
        self::assertNull(SqlDateTimeHelper::findUtc('SELECT NOW()'));
        self::assertNull(SqlDateTimeHelper::findUtc('SELECT my_UTC_TIMESTAMP'));
        self::assertSame(['prefix' => 'UTC_', 'suffix' => 'TIMESTAMP'], SqlDateTimeHelper::findUtc('SELECT UTC_TIMESTAMP()'));
        // The first one is used, the suffix is upper case, and the prefix is as it is written.
        self::assertSame(['prefix' => 'utc_', 'suffix' => 'DATE'], SqlDateTimeHelper::findUtc('SELECT utc_date, UTC_TIME'));
    }

    public function testFindMissingPrecision(): void
    {
        self::assertNull(SqlDateTimeHelper::findMissingPrecision('SELECT NOW(0)', 0));
        self::assertNull(SqlDateTimeHelper::findMissingPrecision('SELECT NOW( 6 )', 6));
        self::assertNull(SqlDateTimeHelper::findMissingPrecision('SELECT 1', 0));
        self::assertSame('NOW', SqlDateTimeHelper::findMissingPrecision('SELECT now()', 0));
        self::assertSame('NOW', SqlDateTimeHelper::findMissingPrecision('SELECT NOW(3)', 0));
        self::assertSame('CURRENT_TIMESTAMP', SqlDateTimeHelper::findMissingPrecision('SELECT CURRENT_TIMESTAMP', 6));
        self::assertSame('DATETIME', SqlDateTimeHelper::findMissingPrecision('CREATE TABLE t (a DATETIME)', 0));
    }

    /**
     * @return array<string, array{string, int, list<array{length: int, offset: int, replacement: string, target: string, type: string}>}>
     */
    public static function precisionEditsProvider(): array
    {
        return [
            'nothing to fix' => ['SELECT NOW(0)', 0, []],
            'no parentheses' => ['SELECT NOW', 0, [['length' => 3, 'offset' => 7, 'replacement' => 'NOW(0)', 'target' => '0', 'type' => 'precision']]],
            'empty parentheses' => ['SELECT NOW()', 6, [['length' => 5, 'offset' => 7, 'replacement' => 'NOW(6)', 'target' => '6', 'type' => 'precision']]],
            'a wrong digit and spaces are replaced' => ['SELECT NOW( 3 )', 0, [['length' => 8, 'offset' => 7, 'replacement' => 'NOW(0)', 'target' => '0', 'type' => 'precision']]],
            'the case of the keyword is kept' => ['SELECT now()', 0, [['length' => 5, 'offset' => 7, 'replacement' => 'now(0)', 'target' => '0', 'type' => 'precision']]],
            'only the wrong one in a string' => ['SELECT NOW(0), CURTIME()', 0, [['length' => 9, 'offset' => 15, 'replacement' => 'CURTIME(0)', 'target' => '0', 'type' => 'precision']]],
        ];
    }

    /**
     * @param list<array{length: int, offset: int, replacement: string, target: string, type: string}> $expected
     */
    #[DataProvider('precisionEditsProvider')]
    public function testCollectPrecisionEdits(string $masked, int $precision, array $expected): void
    {
        self::assertSame($expected, SqlDateTimeHelper::collectPrecisionEdits($masked, $precision));
    }

    /**
     * @return array<string, array{string, list<array{length: int, offset: int, replacement: string, type: string}>}>
     */
    public static function utcEditsProvider(): array
    {
        return [
            'nothing to fix' => ['SELECT NOW()', []],
            'upper case' => ['SELECT UTC_DATE', [['length' => 8, 'offset' => 7, 'replacement' => 'CURRENT_DATE', 'type' => 'utc']]],
            'lower case' => ['SELECT utc_time', [['length' => 8, 'offset' => 7, 'replacement' => 'current_time', 'type' => 'utc']]],
            'the case of the suffix is kept' => ['SELECT UTC_date, utc_DATE', [
                ['length' => 8, 'offset' => 7, 'replacement' => 'CURRENT_date', 'type' => 'utc'],
                ['length' => 8, 'offset' => 17, 'replacement' => 'current_DATE', 'type' => 'utc'],
            ]],
        ];
    }

    /**
     * @param list<array{length: int, offset: int, replacement: string, type: string}> $expected
     */
    #[DataProvider('utcEditsProvider')]
    public function testCollectUtcEdits(string $masked, array $expected): void
    {
        self::assertSame($expected, SqlDateTimeHelper::collectUtcEdits($masked));
    }

    public function testMergeUtcWithPrecision(): void
    {
        $masked = 'SELECT UTC_TIMESTAMP, UTC_DATE, NOW()';
        $merged = SqlDateTimeHelper::mergeUtcWithPrecision(
            SqlDateTimeHelper::collectUtcEdits($masked),
            SqlDateTimeHelper::collectPrecisionEdits($masked, 2),
        );

        // `UTC_TIMESTAMP` needs both fixes at the same place: they are one edit. `UTC_DATE` needs no precision. `NOW` is not a `UTC_*` function: it is not in the result.
        self::assertSame(
            [
                ['length' => 13, 'offset' => 7, 'replacement' => 'CURRENT_TIMESTAMP(2)', 'type' => 'merged'],
                ['length' => 8, 'offset' => 22, 'replacement' => 'CURRENT_DATE', 'type' => 'utc'],
            ],
            $merged,
        );
    }
}
