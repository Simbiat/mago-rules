<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\PHP;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Flags exit/die used without an explicit status code, and auto-fixes it to
 * exit(0). Covers both `exit()`/`die()` (empty call, the original rule's
 * exact scope) and the bare `exit;`/`die;` form, which the original SSR
 * rule could not match because it required call syntax.
 *
 * Ported from the "Use exit function with exit code" rule.
 */
final class ExitCodeRule implements Rule
{
    /**
     * Code of the rule, as used in `mago.toml`, `--only`, and `@mago-ignore lint:`.
     */
    public const string CODE = 'simbiat/exit-code';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: self::CODE,
            name: 'Exit code',
            description: 'Suggests adding a status code to exit/die without one, for the sake of being explicit.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            // Mago has separate nodes for `exit` and `die`. PHPCS had one token (`T_EXIT`) for both.
            targets: [NodeKind::ExitConstruct, NodeKind::DieConstruct],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $keyword = null;
        $arguments = null;
        foreach ($context->getChildren() as $child) {
            if ($child->kind === NodeKind::Keyword) {
                $keyword = $child;
            } elseif ($child->kind === NodeKind::ArgumentList) {
                $arguments = $child;
            }
        }

        if ($keyword === null) {
            return;
        }

        $keyword_text = $file->getText($keyword);
        // The token is `exit` or `die` in any ASCII case, so `strtolower()` is correct and `mb_strtolower()` is not needed.
        // phpcs:ignore Simbiat.Strings.PreferMultibyte -- A keyword is not text.
        $suggestion = \strtolower($keyword_text);

        // Bare `exit;` / `die;` — no parentheses at all.
        if ($arguments === null) {
            $context->report(
                Issue::new(
                    \sprintf('%s should be called with an explicit exit code, e.g. %s(0).', $keyword_text, $suggestion),
                    $context->node->span,
                )->withEdit(TextEdit::insert($keyword->span->end, '(0)')),
            );

            return;
        }

        // Anything between the parens except whitespace (so a comment counts, too)
        // is "an argument" — only an empty exit()/die() is flagged.
        $inner = $file->getText(new \Mago\Sdk\Span($arguments->span->start + 1, $arguments->span->end - 1));
        if (\mb_trim($inner, encoding: 'UTF-8') !== '') {
            return;
        }

        $context->report(
            Issue::new(
                \sprintf('%s() should be called with an explicit exit code, e.g. %s(0).', $keyword_text, $suggestion),
                $context->node->span,
            )->withEdit(TextEdit::insert($arguments->span->start + 1, '0')),
        );
    }
}
