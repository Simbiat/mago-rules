<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Regex;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;
use Simbiat\MagoRules\Helpers\PregCallHelper;
use Simbiat\MagoRules\Helpers\RegexLiteralHelper;

/**
 * Flags preg_* calls whose literal pattern is missing the `u` (Unicode)
 * flag. Auto-fixable — appends `u` to the pattern's flags.
 *
 * Ported from the "Missing Unicode flag" rule.
 */
final class MissingUnicodeFlagRule implements Rule
{
    /**
     * Code of the rule, as used in `mago.toml`, `--only`, and `@mago-ignore lint:`.
     */
    public const string CODE = 'simbiat/missing-unicode-flag';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: self::CODE,
            name: 'Missing Unicode flag',
            description: 'Highlights preg_*() literal patterns missing the u flag. Useful in projects dealing with unicode.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    public function lint(LintContext $context): void
    {
        $pattern = PregCallHelper::findLiteralPattern($context);
        if (
            $pattern === null
            || \str_contains($pattern['flags'], 'u')
        ) {
            return;
        }

        $context->report(
            Issue::new(
                \sprintf("Pattern for %s() is missing the Unicode ('u') flag.", $pattern['function']),
                $pattern['node']->span,
            )->withEdit(TextEdit::replace($pattern['node']->span, RegexLiteralHelper::appendFlag($pattern['content'], 'u'))),
        );
    }
}
