<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\Helpers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Simbiat\MagoRules\Helpers\CallHelper;
use Simbiat\MagoRules\Helpers\PregCallHelper;
use Simbiat\MagoRules\Helpers\RegexLiteralHelper;
use Simbiat\MagoRules\Regex\MissingUnicodeFlagRule;
use Simbiat\MagoRules\Tests\Support\MagoRunner;

/**
 * The helper needs a syntax tree from Mago, so its behavior is checked through a rule that uses it (`simbiat/missing-unicode-flag`).
 * The rule reports a pattern without the `u` flag, so "is reported" means "the helper found a literal pattern".
 */
#[CoversClass(PregCallHelper::class)]
#[UsesClass(CallHelper::class)]
#[UsesClass(RegexLiteralHelper::class)]
#[UsesClass(MissingUnicodeFlagRule::class)]
final class PregCallHelperTest extends TestCase
{
    /**
     * Every preg_* function of PHP takes a pattern, except these three.
     * If PHP adds a new one, this test fails, and tells you to add it to the list.
     */
    #[CoversNothing]
    public function testFunctionListHasEveryPatternFunction(): void
    {
        $expected = \array_values(\array_diff(
            \array_keys((new \ReflectionExtension('pcre'))->getFunctions()),
            ['preg_last_error', 'preg_last_error_msg', 'preg_quote'],
        ));
        $actual = PregCallHelper::FUNCTIONS;

        \sort($expected);
        \sort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{string, bool}> Call => is the literal pattern found?
     */
    public static function literalPatternProvider(): array
    {
        return [
            'single quotes' => ["preg_match('/foo/i', \$s)", true],
            'no flags' => ["preg_split('#a#', \$s)", true],
            'double quotes' => ["preg_match(\"/foo/i\", \$s)", true],
            'upper case name' => ["PREG_REPLACE('/a/', 'b', \$s)", true],
            'fully-qualified name' => ["\\preg_match('/a/', \$s)", true],
            'qualified name' => ["Foo\\preg_match('/a/', \$s)", false],
            'method call' => ["\$o->preg_match('/a/', \$s)", false],
            'static call' => ["Foo::preg_match('/a/', \$s)", false],
            'variable pattern' => ['preg_match($pattern, $s)', false],
            'concatenated pattern' => ["preg_match('/a/' . \$x, \$s)", false],
            'interpolated pattern' => ["preg_match(\"/a/{\$x}\", \$s)", false],
            'no second argument' => ["preg_match('/a/')", false],
            'named pattern' => ["preg_match(pattern: '/a/', subject: \$s)", false],
            'unpacked arguments' => ['preg_match(...$arguments)', false],
            'delimiters that are not understood' => ["preg_match('(a)i', \$s)", false],
            'not a preg function' => ["strlen('/a/', \$s)", false],
            'preg function without a pattern' => ["preg_quote('/a/', '/')", false],
            'the pattern is not the first argument' => ["preg_replace_callback_array(['/a/' => \$f], \$s)", false],
        ];
    }

    #[DataProvider('literalPatternProvider')]
    public function testFindLiteralPattern(string $call, bool $expected): void
    {
        $issues = MagoRunner::lint('<?php '.$call.';', ['simbiat/missing-unicode-flag']);

        self::assertSame($expected ? ['simbiat/missing-unicode-flag'] : [], \array_column($issues, 'code'));
    }
}
