<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\Regex;

use PHPUnit\Framework\Attributes\CoversNothing;
use Simbiat\MagoRules\Tests\RuleTestCase;
use Simbiat\MagoRules\Tests\Support\MagoRunner;

#[CoversNothing]
final class MissingCaselessRestrictFlagTest extends RuleTestCase
{
    public static function ruleCodes(): array
    {
        return [
            'simbiat/missing-caseless-restrict-flag' => true,
        ];
    }

    /**
     * The message names the function in lower case, however it is written. The fixtures check only the codes of the results.
     */
    public function testMessageNamesTheFunction(): void
    {
        $issues = MagoRunner::lint("<?php\nPREG_MATCH('/a/ui', \$s);\n\\preg_match('/a/ui', \$s);\n", ['simbiat/missing-caseless-restrict-flag']);

        self::assertSame(["Pattern for preg_match() combines 'u' and 'i' without the caseless restrict ('r') flag.", "Pattern for preg_match() combines 'u' and 'i' without the caseless restrict ('r') flag."], \array_column($issues, 'message'));
    }
}
