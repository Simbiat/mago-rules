<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\PHP;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Simbiat\MagoRules\Helpers\CallHelper;
use Simbiat\MagoRules\Helpers\PregCallHelper;

/**
 * Suggests \Simbiat\StringHelpers\Sanitize::whiteString($subject) instead
 * of a hand-rolled preg_match('/^\s*$/[flags]', $subject, $matches) === 1
 * (or !== 1) whitespace-only check.
 *
 * Suggestion only, deliberately not auto-fixed: unlike the other fixable
 * rules here, this one changes which function actually runs the check -
 * worth a human confirming whiteString()'s behavior matches what the
 * call site actually needs before swapping it in.
 *
 * Only matches a literal pattern of exactly /^\s*$/ or /^\s+$/, single- or
 * double-quoted, plus optional flags from `imsxeADSUXJur` - a differently-
 * shaped core pattern is left alone.
 *
 * Ported from the "Prefer `whiteString()`" rule.
 */
final class PreferWhiteStringRule implements Rule
{
    /**
     * Code of the rule, as used in `mago.toml`, `--only`, and `@mago-ignore lint:`.
     */
    public const string CODE = 'simbiat/prefer-white-string';

    /**
     * The class that has `whiteString()`. It is a string on purpose: the class does not have to be installed next to this package.
     */
    private const string SANITIZE_CLASS = 'Simbiat\StringHelpers\Sanitize';

    /**
     * Allowed PCRE2 flags.
     */
    private const string ALLOWED_FLAGS = 'imsxeADSUXJur';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: self::CODE,
            name: 'Prefer whiteString',
            description: 'Suggests Sanitize::whiteString() instead of preg_match(\'/^\s*$/\', $string) === 1 to capture more whitespace characters.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            // The rule looks at the comparison, not at the call: the call alone does not know what is done with its result.
            targets: [NodeKind::Binary],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        // A binary expression has three children: the left operand, the operator and the right operand.
        $parts = $context->getChildren();
        if (\count($parts) !== 3 || $parts[1]->kind !== NodeKind::BinaryOperator) {
            return;
        }

        // Comparison operator right after the call: === 1 or !== 1.
        $operator = $file->getText($parts[1]);
        if ($operator !== '===' && $operator !== '!==') {
            return;
        }

        // Only the integer literal `1` has the text `1`, so the text check is enough.
        if ($file->getText($parts[2]) !== '1') {
            return;
        }

        $call_node = self::unwrapToFunctionCall($context, $parts[0]);
        if ($call_node === null) {
            return;
        }

        // Checks the function name (without the leading backslash), that it is a call of the global function, and the arguments.
        $call_context = new LintContext($file, $call_node, $context->cancellation);
        $call = CallHelper::findCall($call_context, ['preg_match']);
        if ($call === null) {
            return;
        }

        // A literal pattern, and a second argument (the subject) must be present.
        $pattern = PregCallHelper::findPatternNode($call_context, $call['call']);
        if ($pattern === null || !self::isWhitespaceOnlyPattern($file->getText($pattern))) {
            return;
        }

        // The whole argument, as the parser sees it: a comma inside a nested call does not cut it.
        $subject = \mb_trim($file->getText($call['call']->arguments[1]->node), encoding: 'UTF-8');

        $negated = $operator === '!==';
        $suggestion = ($negated ? '!' : '').self::SANITIZE_CLASS.'::whiteString('.$subject.')';

        $context->report(
            Issue::new(
                \sprintf('Consider %s instead of a hand-rolled whitespace-only regex check.', $suggestion),
                $call_node->span,
            ),
        );
    }

    /**
     * Content is the raw literal text, quotes included, e.g. '/^\s*$/ui' or
     * "/^\s+$/". True for either quote style, and for either /^\s*$/ or
     * /^\s+$/ as the pattern core, with flags drawn exclusively from
     * ALLOWED_FLAGS.
     *
     * @param string $content
     *
     * @return bool
     */
    private static function isWhitespaceOnlyPattern(string $content): bool
    {
        // Strip the surrounding quote char. The quotes are ASCII, and so is the rest of what is compared below.
        // phpcs:ignore Simbiat.Strings.PreferMultibyte -- See above.
        $inner = \substr($content, 1, -1);
        $core = \array_find(['/^\s*$/', '/^\s+$/'], static fn($candidate): bool => \str_starts_with($inner, $candidate));

        if ($core === null) {
            return false;
        }

        // phpcs:ignore Simbiat.Strings.PreferMultibyte -- See above.
        $flags = \substr($inner, \strlen($core));

        // Every character of the flags must be one of the allowed flags. An empty string means "no flags", which is fine.
        return \strspn($flags, self::ALLOWED_FLAGS) === \strlen($flags);
    }

    /**
     * Go down from an operand to the function call inside it: `Expression` > `Call` > `FunctionCall`.
     *
     * @param LintContext $context
     * @param Node        $operand
     *
     * @return Node|null Null if the operand is anything else than a plain function call.
     */
    private static function unwrapToFunctionCall(LintContext $context, Node $operand): ?Node
    {
        $node = $operand;
        while ($node->kind === NodeKind::Expression || $node->kind === NodeKind::Call) {
            $node = $context->file->getChildren($node)[0] ?? null;
            if ($node === null) {
                return null;
            }
        }

        return $node->kind === NodeKind::FunctionCall ? $node : null;
    }
}
