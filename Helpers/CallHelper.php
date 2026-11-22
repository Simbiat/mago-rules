<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Helpers;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

/**
 * Small shared helpers for rules that need to inspect a plain function call:
 * telling it apart from a method call/definition, and looking at its arguments.
 *
 * Mago parses the code and gives the rules a syntax tree, so (unlike the PHPCS version of these helpers)
 * nothing here splits tokens or counts parentheses by hand.
 */
final class CallHelper
{
    /**
     * Check that the node of the context is a call of one of the given functions.
     *
     * Both `strlen(...)` and `\strlen(...)` are accepted.
     * Method calls and static calls are different node kinds, so they never match, and function definitions are not calls at all.
     * The rule must have `NodeKind::FunctionCall` in its targets.
     *
     * @param LintContext              $context
     * @param array<int|string, mixed> $functions Lowercase function names: values of a list, or keys of an array.
     *
     * @return array{name: string, call: CallExpression}|null `name` is lowercase, without a leading backslash.
     */
    public static function findCall(LintContext $context, array $functions): ?array
    {
        if ($context->node->kind !== NodeKind::FunctionCall) {
            return null;
        }

        $call = CallExpression::fromNode($context->file, $context->node);
        $callee = $call->getName($context->file);
        if ($callee === null) {
            return null;
        }

        // PHP folds the case of function names for ASCII letters only, so `strtolower()` is the right function here, not `mb_strtolower()`.
        // phpcs:ignore Simbiat.Strings.PreferMultibyte -- A function name is not text.
        $name = \strtolower(\ltrim($callee, '\\'));

        $is_listed = \array_is_list($functions)
            ? \in_array($name, $functions, strict: true)
            : \array_key_exists($name, $functions);
        if (!$is_listed) {
            return null;
        }

        return ['name' => $name, 'call' => $call];
    }

    /**
     * Check if one of the arguments is named `encoding`: `mb_strlen($a, encoding: 'UTF-8')`.
     *
     * @param CallExpression $call
     *
     * @return bool
     */
    public static function hasNamedEncoding(CallExpression $call): bool
    {
        return \array_any($call->arguments, static fn($argument): bool => $argument->name === 'encoding');
    }

    /**
     * Find the offset of the closing parenthesis of a call. An edit made at this offset goes right before the parenthesis.
     *
     * @param CallExpression $call
     *
     * @return int
     */
    public static function closeParenthesisOffset(CallExpression $call): int
    {
        // The call ends with its closing parenthesis.
        return $call->node->span->end - 1;
    }

    /**
     * Check if the last thing in the parentheses is a comma: `f($a, $b,)`. Whitespace and comments do not count.
     *
     * @param SourceFile     $file
     * @param CallExpression $call
     *
     * @return bool
     */
    public static function hasTrailingComma(SourceFile $file, CallExpression $call): bool
    {
        // `end()` would take the property by reference, and it is read-only.
        $last = $call->arguments === [] ? null : $call->arguments[\array_key_last($call->arguments)];
        if ($last === null) {
            return false;
        }

        $tail = $file->getText(new Span($last->node->span->end, self::closeParenthesisOffset($call)));
        // The comments may have commas inside them. The pattern has the `u` flag, and if the text is not valid UTF-8, the unchanged text is checked.
        $without_comments = \preg_replace('~/\*.*?\*/|//[^\n]*|#[^\n]*~su', '', $tail) ?? $tail;

        return \str_contains($without_comments, ',');
    }

    /**
     * Get the literal string (a quoted string without interpolation) from an expression.
     *
     * @param SourceFile $file
     * @param Node       $expression The value of an argument.
     *
     * @return Node|null The `LiteralString` node. Null for anything else: a variable, a constant, a concatenation, a string with interpolation.
     */
    public static function literalString(SourceFile $file, Node $expression): ?Node
    {
        if ($expression->kind === NodeKind::LiteralString) {
            return $expression;
        }

        if ($expression->kind !== NodeKind::Literal) {
            return null;
        }

        $inner = $file->getChildren($expression)[0] ?? null;

        return $inner !== null && $inner->kind === NodeKind::LiteralString ? $inner : null;
    }
}
