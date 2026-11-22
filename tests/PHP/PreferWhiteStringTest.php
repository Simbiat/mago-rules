<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\PHP;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Simbiat\MagoRules\Helpers\CallHelper;
use Simbiat\MagoRules\Helpers\PregCallHelper;
use Simbiat\MagoRules\PHP\PreferWhiteStringRule;
use Simbiat\MagoRules\Tests\RuleTestCase;
use Simbiat\MagoRules\Tests\Support\MagoRunner;

#[CoversClass(PreferWhiteStringRule::class)]
#[UsesClass(CallHelper::class)]
#[UsesClass(PregCallHelper::class)]
final class PreferWhiteStringTest extends RuleTestCase
{
    public static function ruleCodes(): array
    {
        return [
            'simbiat/prefer-white-string' => false,
        ];
    }

    public function testSuggestionContainsTheSubjectAndNegation(): void
    {
        $code = <<<'PHP'
            <?php
            $a = preg_match('/^\s*$/u', $value) === 1;
            $b = preg_match('/^\s*$/u', $this->getValue()) !== 1;
            $c = preg_match('/^\s*$/u', $key, $matches) === 1;
            $d = preg_match('/^\s*$/u', $this->getValue($x, $y)) === 1;
            PHP;
        $messages = [];
        foreach (MagoRunner::lint($code, ['simbiat/prefer-white-string']) as $issue) {
            $messages[$issue['line']][] = $issue['message'];
        }

        self::assertSame(
            ['Consider Simbiat\StringHelpers\Sanitize::whiteString($value) instead of a hand-rolled whitespace-only regex check.'],
            $messages[2],
        );
        self::assertSame(
            ['Consider !Simbiat\StringHelpers\Sanitize::whiteString($this->getValue()) instead of a hand-rolled whitespace-only regex check.'],
            $messages[3],
        );
        // The subject ends at the comma: the `$matches` argument is not a part of it.
        self::assertSame(
            ['Consider Simbiat\StringHelpers\Sanitize::whiteString($key) instead of a hand-rolled whitespace-only regex check.'],
            $messages[4],
        );
        // The parser knows where an argument ends, so a comma inside a nested call does not cut the subject (the token based version did).
        self::assertSame(
            ['Consider Simbiat\StringHelpers\Sanitize::whiteString($this->getValue($x, $y)) instead of a hand-rolled whitespace-only regex check.'],
            $messages[5],
        );
    }

    public function testBrokenCodeIsIgnored(): void
    {
        $issues = MagoRunner::lint('<?php preg_match', ['simbiat/prefer-white-string']);

        self::assertSame([], \array_values(\array_filter($issues, static fn(array $issue): bool => $issue['code'] === 'simbiat/prefer-white-string')));
    }
}
