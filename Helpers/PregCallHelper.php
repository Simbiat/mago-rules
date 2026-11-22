<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Helpers;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;

/**
 * Finds the pattern argument of a `preg_*(...)` call, when it's a plain
 * string literal (not a variable/constant/expression — those are left
 * alone, same as the original rules).
 */
final class PregCallHelper
{
    /**
     * List of functions to check.
     */
    public const array FUNCTIONS = [
        'preg_filter',
        'preg_grep',
        'preg_match',
        'preg_match_all',
        'preg_replace',
        'preg_replace_callback',
        'preg_replace_callback_array',
        'preg_split',
    ];

    /**
     * Everything that the regex rules need to know about a `preg_*()` call with a literal pattern.
     *
     * @param LintContext $context
     *
     * @return array{function: string, node: Node, content: string, flags: string}|null
     *         `function` is lowercase, `node` and `content` are of the pattern literal, `flags` are its flags.
     *         Null if this is not a `preg_*()` call with a literal pattern that can be understood.
     */
    public static function findLiteralPattern(LintContext $context): ?array
    {
        $call = CallHelper::findCall($context, self::FUNCTIONS);
        if ($call === null) {
            return null;
        }

        $pattern = self::findPatternNode($context, $call['call']);
        if ($pattern === null) {
            return null;
        }

        $content = $context->file->getText($pattern);
        $parsed = RegexLiteralHelper::parse($content);
        if ($parsed === null) {
            return null;
        }

        return ['function' => $call['name'], 'node' => $pattern, 'content' => $content, 'flags' => $parsed['flags']];
    }

    /**
     * @param LintContext    $context
     * @param CallExpression $call
     *
     * @return Node|null The `LiteralString` node of the pattern, or null if the first argument is not a literal,
     *                   or if there is no further argument.
     */
    public static function findPatternNode(LintContext $context, CallExpression $call): ?Node
    {
        // Require at least one more argument after the pattern, matching
        // the original rules' `$func$($pattern$, $arg$)` shape.
        if (\count($call->arguments) < 2) {
            return null;
        }

        $first = $call->arguments[0];
        // A named or unpacked first argument is not "the pattern": `preg_match(pattern: '/a/', ...)`, `preg_match(...$args)`.
        if ($first->name !== null || $first->unpacked) {
            return null;
        }

        return CallHelper::literalString($context->file, $first->value);
    }
}
