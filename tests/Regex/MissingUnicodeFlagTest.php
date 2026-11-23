<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\Regex;

use PHPUnit\Framework\Attributes\CoversNothing;
use Simbiat\MagoRules\Tests\RuleTestCase;
use Simbiat\MagoRules\Tests\Support\MagoRunner;

#[CoversNothing]
final class MissingUnicodeFlagTest extends RuleTestCase
{
    public static function ruleCodes(): array
    {
        return [
            'simbiat/missing-unicode-flag' => true,
        ];
    }

    /**
     * The message names the function in lower case, however it is written. The fixtures check only the codes of the results.
     */
    public function testMessageNamesTheFunction(): void
    {
        $issues = MagoRunner::lint("<?php\nPREG_MATCH('/a/', \$s);\n\\preg_match('/a/', \$s);\n", ['simbiat/missing-unicode-flag']);

        self::assertSame(["Pattern for preg_match() is missing the Unicode ('u') flag.", "Pattern for preg_match() is missing the Unicode ('u') flag."], \array_column($issues, 'message'));
    }
}
