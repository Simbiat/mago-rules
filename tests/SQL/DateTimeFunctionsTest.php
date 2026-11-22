<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\SQL;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Simbiat\MagoRules\Helpers\SqlDateTimeHelper;
use Simbiat\MagoRules\SQL\MissingPrecisionRule;
use Simbiat\MagoRules\SQL\PreferCurrentRule;
use Simbiat\MagoRules\Tests\RuleTestCase;
use Simbiat\MagoRules\Tests\Support\MagoRunner;

/**
 * Both SQL rules share the fixtures, because the fix of one can touch the same place as the fix of the other.
 * The precision is set per fixture, with a `// @property precision=N` directive.
 */
#[CoversClass(PreferCurrentRule::class)]
#[CoversClass(MissingPrecisionRule::class)]
#[UsesClass(SqlDateTimeHelper::class)]
final class DateTimeFunctionsTest extends RuleTestCase
{
    public static function ruleCodes(): array
    {
        return [
            'simbiat/sql-prefer-current' => true,
            'simbiat/sql-missing-precision' => true,
        ];
    }

    /**
     * A `UTC_*` function is fixed by `simbiat/sql-prefer-current` (together with its precision), so the precision rule has no edit of its own for it.
     */
    protected static function mayBeUnfixable(): array
    {
        return ['simbiat/sql-missing-precision'];
    }

    /**
     * The messages name the function in upper case, and keep the written case of the `UTC_` prefix.
     */
    public function testMessagesNameTheFunctions(): void
    {
        $code = <<<'PHP'
            <?php
            $a = 'SELECT UTC_TIMESTAMP() FROM t';
            $b = 'select utc_time from t';
            $c = 'SELECT now() FROM t';
            $d = 'SELECT CURTIME() FROM t';
            PHP;
        $messages = [];
        foreach (MagoRunner::lint($code, \array_keys(self::ruleCodes())) as $issue) {
            $messages[$issue['line']][] = $issue['message'];
        }

        $texts = static function (array $line): array {
            \sort($line);

            return $line;
        };

        self::assertSame(
            [
                'UTC_TIMESTAMP should explicitly state (0) fractional-second precision.',
                'Use CURRENT_TIMESTAMP instead of UTC_TIMESTAMP.',
            ],
            $texts($messages[2]),
        );
        self::assertSame(
            [
                'UTC_TIME should explicitly state (0) fractional-second precision.',
                'Use CURRENT_TIME instead of utc_TIME.',
            ],
            $texts($messages[3]),
        );
        self::assertSame(['NOW should explicitly state (0) fractional-second precision.'], $texts($messages[4]));
        self::assertSame(['CURTIME should explicitly state (0) fractional-second precision.'], $texts($messages[5]));
    }

    /**
     * An interpolated part is code, not SQL: nothing inside of it is changed, even if it looks like a function that needs a change.
     */
    public function testInterpolatedCodeIsNotChanged(): void
    {
        $code = <<<'PHP'
            <?php
            $a = "SELECT NOW() FROM t WHERE x = {$db->utc_timestamp('NOW()')}";
            PHP;
        $result = MagoRunner::fix($code, \array_keys(self::ruleCodes()));

        self::assertSame(
            <<<'PHP'
            <?php
            $a = "SELECT NOW(0) FROM t WHERE x = {$db->utc_timestamp('NOW()')}";
            PHP,
            $result['contents'],
        );
    }

    /**
     * The worker takes the precision from its command line, and the message names it.
     */
    public function testPrecisionComesFromTheWorkerOption(): void
    {
        $issues = MagoRunner::lint("<?php\n\$a = 'SELECT NOW() FROM t';\n", ['simbiat/sql-missing-precision'], 3);

        self::assertSame(['NOW should explicitly state (3) fractional-second precision.'], \array_column($issues, 'message'));
    }
}
