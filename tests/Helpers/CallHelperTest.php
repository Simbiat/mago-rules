<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\Helpers;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simbiat\MagoRules\Tests\Support\MagoRunner;

/**
 * The helper needs a syntax tree from Mago, so its behavior is checked through the rules that use it:
 * `simbiat/prefer-multibyte` (is a function call found?) and `simbiat/multibyte-encoding` (arguments, named arguments, the trailing comma).
 */
#[CoversNothing]
final class CallHelperTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}> Code => is a call of `strlen` found?
     */
    public static function functionCallProvider(): array
    {
        return [
            'plain call' => ['strlen($a);', true],
            'fully-qualified call' => ['\strlen($a);', true],
            'upper case' => ['STRLEN($a);', true],
            'qualified call' => ['Foo\strlen($a);', false],
            'fully-qualified call of a namespaced function' => ['\Foo\strlen($a);', false],
            'method call' => ['$o->strlen($a);', false],
            'null-safe method call' => ['$o?->strlen($a);', false],
            'static call' => ['Foo::strlen($a);', false],
            'function definition' => ['function strlen($a) {}', false],
            'method definition' => ['class Foo { public function strlen($a) {} }', false],
            'dynamic call' => ['$strlen($a);', false],
            'string callable' => ["'strlen'(\$a);", false],
            'first-class callable' => ['strlen(...);', false],
        ];
    }

    #[DataProvider('functionCallProvider')]
    public function testFindCall(string $code, bool $expected): void
    {
        $issues = MagoRunner::lint('<?php '.$code, ['simbiat/prefer-multibyte']);

        self::assertSame($expected ? ['simbiat/prefer-multibyte'] : [], \array_column($issues, 'code'));
    }

    /**
     * @return array<string, array{string, string}> Code => the code after the fix of `simbiat/multibyte-encoding`.
     */
    public static function encodingFixProvider(): array
    {
        return [
            'no arguments at all' => ['mb_strlen()', "mb_strlen(encoding: 'UTF-8')"],
            'one argument' => ['mb_strlen($a)', "mb_strlen(\$a, encoding: 'UTF-8')"],
            // The comma already separates the new argument from the last one, so nothing is added before it, not even a space.
            'trailing comma' => ['mb_strlen($a,)', "mb_strlen(\$a,encoding: 'UTF-8')"],
            'trailing comma and a space' => ['mb_strlen($a, )', "mb_strlen(\$a, encoding: 'UTF-8')"],
            'trailing comma and line breaks' => ["mb_strlen(\n    \$a,\n)", "mb_strlen(\n    \$a,\nencoding: 'UTF-8')"],
            'trailing comma and a comment' => ['mb_strlen($a, /* last */)', "mb_strlen(\$a, /* last */encoding: 'UTF-8')"],
            'comma only inside a comment' => ['mb_strlen($a /* last, */)', "mb_strlen(\$a /* last, */, encoding: 'UTF-8')"],
            'comma only inside a nested call' => ['mb_strlen(g($a,))', "mb_strlen(g(\$a,), encoding: 'UTF-8')"],
            'comma inside a short array' => ['mb_strlen([1, 2])', "mb_strlen([1, 2], encoding: 'UTF-8')"],
            'trailing comma after a short array' => ['mb_strlen([1, 2],)', "mb_strlen([1, 2],encoding: 'UTF-8')"],
            'closure with commas inside' => ['mb_strlen(function ($x, $y) { return [$x, $y]; })', "mb_strlen(function (\$x, \$y) { return [\$x, \$y]; }, encoding: 'UTF-8')"],
            'string with a comma' => ["mb_strlen('a,b')", "mb_strlen('a,b', encoding: 'UTF-8')"],
        ];
    }

    #[DataProvider('encodingFixProvider')]
    public function testArgumentsAndTrailingComma(string $code, string $expected): void
    {
        $result = MagoRunner::fix('<?php '.$code.';', ['simbiat/multibyte-encoding']);

        self::assertSame('<?php '.$expected.';', $result['contents']);
    }

    /**
     * @return array<string, array{string, bool}> Code => is the call reported?
     */
    public static function namedEncodingProvider(): array
    {
        return [
            'named encoding' => ["mb_strlen(\$a, encoding: 'UTF-8');", false],
            'named encoding first' => ["mb_strlen(encoding: 'UTF-8', string: \$a);", false],
            'named, but not the encoding' => ['mb_strlen(string: $a);', true],
            'named encoding that is not the last' => ["mb_substr(\$a, 0, encoding: 'UTF-8');", false],
            'positional encoding' => ["mb_strlen(\$a, 'UTF-8');", false],
            'unpacked arguments' => ['mb_strlen(...$arguments);', true],
        ];
    }

    #[DataProvider('namedEncodingProvider')]
    public function testHasNamedEncoding(string $code, bool $expectedReported): void
    {
        $issues = MagoRunner::lint('<?php '.$code, ['simbiat/multibyte-encoding']);

        self::assertSame($expectedReported ? ['simbiat/multibyte-encoding'] : [], \array_column($issues, 'code'));
    }
}
