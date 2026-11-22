<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\PHP;

use PHPUnit\Framework\Attributes\CoversClass;
use Simbiat\MagoRules\PHP\ExitCodeRule;
use Simbiat\MagoRules\Tests\RuleTestCase;
use Simbiat\MagoRules\Tests\Support\MagoRunner;

#[CoversClass(ExitCodeRule::class)]
final class ExitCodeTest extends RuleTestCase
{
    public static function ruleCodes(): array
    {
        return [
            'simbiat/exit-code' => true,
        ];
    }

    /**
     * The message keeps the case of the keyword as it is written, but the suggested replacement is always in lower case.
     * The fixtures check only the codes of the results, so only a test of the messages can see the case.
     */
    public function testSuggestionIsInLowerCase(): void
    {
        $code = <<<'PHP'
            <?php
            EXIT;
            Die();
            exit;
            PHP;
        $messages = [];
        foreach (MagoRunner::lint($code, ['simbiat/exit-code']) as $issue) {
            $messages[$issue['line']][] = $issue['message'];
        }

        self::assertSame(['EXIT should be called with an explicit exit code, e.g. exit(0).'], $messages[2]);
        self::assertSame(['Die() should be called with an explicit exit code, e.g. die(0).'], $messages[3]);
        self::assertSame(['exit should be called with an explicit exit code, e.g. exit(0).'], $messages[4]);
    }

    /**
     * The fix keeps the case of the keyword, and puts the code right where it belongs, with the parentheses as they are written.
     */
    public function testFixKeepsTheWrittenForm(): void
    {
        $result = MagoRunner::fix("<?php\nEXIT;\nDie();\nexit( );\n", ['simbiat/exit-code']);

        self::assertSame("<?php\nEXIT(0);\nDie(0);\nexit(0 );\n", $result['contents']);
    }
}
