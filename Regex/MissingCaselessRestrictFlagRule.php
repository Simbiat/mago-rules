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
 * Flags preg_* calls whose literal pattern combines the `u` and `i` flags
 * without the caseless-restrict `r` flag (PCRE2's `u`+`i` without `r` does
 * full Unicode case-folding, which is slower and can match more than you
 * expect — `r` restricts it to simple case folding). Auto-fixable —
 * appends `r` to the pattern's flags.
 *
 * Only fires once a pattern already has both `u` and `i` — if it's also
 * missing `u`, MissingUnicodeFlagRule catches that first and this one
 * will pick it up on the following run, same two-pass behavior as the
 * original SSR rules.
 *
 * Ported from the "Missing caseless restrict flag" rule.
 */
final class MissingCaselessRestrictFlagRule implements Rule
{
    /**
     * Code of the rule, as used in `mago.toml`, `--only`, and `@mago-ignore lint:`.
     */
    public const string CODE = 'simbiat/missing-caseless-restrict-flag';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: self::CODE,
            name: 'Missing caseless restrict flag',
            description: 'Highlights preg_*() literal patterns with the u and i flags, but without the r flag, to prevent matching of the Kelvin sign for k.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    public function lint(LintContext $context): void
    {
        $pattern = PregCallHelper::findLiteralPattern($context);
        if ($pattern === null) {
            return;
        }

        $flags = $pattern['flags'];
        if (
            !\str_contains($flags, 'u')
            || !\str_contains($flags, 'i')
            || \str_contains($flags, 'r')
        ) {
            return;
        }

        $context->report(
            Issue::new(
                \sprintf("Pattern for %s() combines 'u' and 'i' without the caseless restrict ('r') flag.", $pattern['function']),
                $pattern['node']->span,
            )->withEdit(TextEdit::replace($pattern['node']->span, RegexLiteralHelper::appendFlag($pattern['content'], 'r'))),
        );
    }
}
