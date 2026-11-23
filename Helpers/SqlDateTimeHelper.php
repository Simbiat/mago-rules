<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Helpers;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

/**
 * What the two SQL date/time rules have in common: finding the SQL text in string literals,
 * and finding the places in it that need a change.
 *
 * Both rules look at the same text, and the fix of one can touch the same place as the fix of the other
 * (`UTC_TIMESTAMP` needs `CURRENT_TIMESTAMP` and a precision), so the logic is kept in one place.
 */
final class SqlDateTimeHelper
{
    /**
     * Comments, in the same sense as SQL has them: `-- ...` and `/* ... *\/`. They are masked, so the rules do not see them.
     */
    private const string COMMENT_PATTERN = '/--[^\r\n]*|\/\*.*?\*\//siur';

    /**
     * A function that needs the precision, and is not followed by `(N)` with the wanted N. `%d` is the wanted precision.
     */
    private const string PRECISION_DETECT_PATTERN = '/\b(?:CURRENT_TIMESTAMP|CURRENT_TIME|UTC_TIMESTAMP|UTC_TIME|SYSDATE|NOW|CURTIME|TIMESTAMP|DATETIME)\b(?!\s*\(\s*%d\s*\))/iur';

    /**
     * The same functions, with the optional parentheses and the digit inside them captured.
     */
    private const string PRECISION_FIX_PATTERN = '/\b(CURRENT_TIMESTAMP|CURRENT_TIME|UTC_TIMESTAMP|UTC_TIME|SYSDATE|NOW|CURTIME|TIMESTAMP|DATETIME)\b(\s*\(\s*([0-6]?)\s*\))?/i';

    /**
     * Only a string that starts with one of these is treated as SQL.
     */
    private const string SQL_KEYWORDS = 'SELECT|INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|WITH|CALL';

    /**
     * `UTC_TIMESTAMP`, `UTC_TIME`, `UTC_DATE`.
     */
    private const string UTC_DETECT_PATTERN = '/\b(UTC_)(TIMESTAMP|TIME|DATE)\b/iur';

    /**
     * Find the piece of text, that can be SQL, in the node of the context.
     *
     * A quoted string (`'...'`, `"..."`), with or without interpolation, is one piece, and so is a heredoc. A nowdoc is not checked.
     * That is what PHPCS did, too: it checked `T_CONSTANT_ENCAPSED_STRING`, `T_DOUBLE_QUOTED_STRING` and `T_HEREDOC`, but not `T_NOWDOC`.
     *
     * An interpolated part (`$id`, `{$a['b']}`) is code, not text: it is blanked in `code`, so nothing inside of it is ever changed.
     *
     * @param LintContext $context The rule must have `NodeKind::LiteralString`, `NodeKind::InterpolatedString` and `NodeKind::DocumentString` in its targets.
     *
     * @return array{body: string, code: string, offset: int, span: Span}|null `body` is the text inside the quotes, as it is in the file.
     *                                                                          `code` is the same text, with the interpolated parts replaced by spaces (the length is the same, so the offsets are the same).
     *                                                                          `offset` is where the text starts in the file, `span` is what to point at when reporting.
     *                                                                          Null if the node is a nowdoc, or has no text.
     */
    public static function findBody(LintContext $context): ?array
    {
        $file = $context->file;
        $node = $context->node;

        if ($node->kind === NodeKind::LiteralString) {
            $text = $file->getText($node);
            // The quotes are single ASCII bytes. A quoted string always has two of them. If it is shorter anyway, `substr()` gives an empty body.
            $body = \substr($text, 1, -1);

            return ['body' => $body, 'code' => $body, 'offset' => $node->span->start + 1, 'span' => $node->span];
        }

        if ($node->kind === NodeKind::InterpolatedString) {
            // The text between the quotes. The parts are inside of it. A quoted string has two quotes, so the range is valid.
            return self::fromParts($context, $node->span->start + 1, $node->span->end - 1);
        }

        if ($node->kind !== NodeKind::DocumentString || \preg_match('/^<<<[ \t]*\'/', $file->getText($node)) === 1) {
            return null;
        }

        // The parts of a heredoc are its body: they do not include the opening and the closing label.
        $parts = $context->getChildren();
        if ($parts === []) {
            return null;
        }

        return self::fromParts($context, $parts[0]->span->start, $parts[\array_key_last($parts)]->span->end);
    }

    /**
     * Is the text SQL? A cheap check by the first word.
     *
     * @param string $body
     *
     * @return bool
     */
    public static function looksLikeSql(string $body): bool
    {
        // The keywords are in a group: without it `^` would apply to the first keyword only, and `\b` to the last one only.
        return \preg_match('/^(?:'.self::SQL_KEYWORDS.')\b/i', \mb_ltrim($body, encoding: 'UTF-8')) === 1;
    }

    /**
     * Replace the comments with spaces of the same length (in bytes), so the offsets of everything else stay the same.
     *
     * @param string $body
     *
     * @return string
     */
    public static function mask(string $body): string
    {
        return \preg_replace_callback(
            self::COMMENT_PATTERN,
            static fn(array $m): string => \str_repeat(' ', \strlen($m[0])), // Bytes, not characters: the offsets below are byte offsets.
            $body,
        ) ?? $body;
    }

    /**
     * Keep the precision in the range that MySQL/MariaDB accept for fractional seconds.
     *
     * @param int $precision
     *
     * @return int
     */
    public static function normalisePrecision(int $precision): int
    {
        return \max(0, \min(6, $precision));
    }

    /**
     * @param string $masked
     *
     * @return array{prefix: string, suffix: string}|null The first `UTC_*` found. `suffix` is uppercase.
     */
    public static function findUtc(string $masked): ?array
    {
        if (\preg_match(self::UTC_DETECT_PATTERN, $masked, $match) !== 1) {
            return null;
        }

        // The pattern has the `r` flag, so only ASCII letters can match the keyword: `strtoupper()` is enough.
        return ['prefix' => $match[1], 'suffix' => \strtoupper($match[2])];
    }

    /**
     * @param string $masked
     * @param int    $precision Already normalised.
     *
     * @return string|null The first function that has no (or a wrong) precision, in uppercase.
     */
    public static function findMissingPrecision(string $masked, int $precision): ?string
    {
        if (\preg_match(\sprintf(self::PRECISION_DETECT_PATTERN, $precision), $masked, $match) !== 1) {
            return null;
        }

        return \strtoupper($match[0]);
    }

    /**
     * @param string $masked
     * @param int    $precision Already normalised.
     *
     * @return list<array{length: int, offset: int, replacement: string, target: string, type: string}>
     */
    public static function collectPrecisionEdits(string $masked, int $precision): array
    {
        $edits = [];
        $target = (string)$precision;

        // On "no match" or on a PCRE error preg_match_all() leaves empty arrays in $matches, so the loop does nothing.
        \preg_match_all(self::PRECISION_FIX_PATTERN, $masked, $matches, \PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $iteration => [$full_match, $offset]) {
            $digit = $matches[3][$iteration][0] ?? '';

            if ($digit === $target) {
                continue;
            }

            $keyword = $matches[1][$iteration][0];

            $edits[] = [
                'length' => \strlen($full_match),
                'offset' => $offset,
                'replacement' => $keyword.'('.$target.')',
                'target' => $target,
                'type' => 'precision',
            ];
        }

        return $edits;
    }

    /**
     * @param string $masked
     *
     * @return list<array{length: int, offset: int, replacement: string, type: string}>
     */
    public static function collectUtcEdits(string $masked): array
    {
        $edits = [];

        // On "no match" or on a PCRE error preg_match_all() leaves empty arrays in $matches, so the loop does nothing.
        \preg_match_all(self::UTC_DETECT_PATTERN, $masked, $matches, \PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $iteration => [$full_match, $offset]) {
            $utc_prefix = $matches[1][$iteration][0];
            $suffix = $matches[2][$iteration][0];

            $replacement_prefix = \ctype_upper(\str_replace('_', '', $utc_prefix))
                ? 'CURRENT_'
                : 'current_';

            $edits[] = [
                'length' => \strlen($full_match),
                'offset' => $offset,
                'replacement' => $replacement_prefix.$suffix,
                'type' => 'utc',
            ];
        }

        return $edits;
    }

    /**
     * Where a `UTC_*` function also needs a precision, both fixes want the same place of the text.
     * This makes one edit of them: `UTC_TIMESTAMP` => `CURRENT_TIMESTAMP(0)`.
     *
     * @param list<array{length: int, offset: int, replacement: string, type: string}>                          $utcEdits
     * @param list<array{length: int, offset: int, replacement: string, target: string, type: string}> $precisionEdits
     *
     * @return list<array{length: int, offset: int, replacement: string, type: string}> The `UTC_*` edits, some of them merged with a precision edit.
     */
    public static function mergeUtcWithPrecision(array $utcEdits, array $precisionEdits): array
    {
        $precision_by_offset = [];
        foreach ($precisionEdits as $edit) {
            $precision_by_offset[$edit['offset']] = $edit;
        }

        $merged = [];
        foreach ($utcEdits as $utc) {
            $precision = $precision_by_offset[$utc['offset']] ?? null;
            if ($precision === null) {
                $merged[] = $utc;

                continue;
            }

            $merged[] = [
                'length' => \max($utc['length'], $precision['length']),
                'offset' => $utc['offset'],
                'replacement' => $utc['replacement'].'('.$precision['target'].')',
                'type' => 'merged',
            ];
        }

        return $merged;
    }

    /**
     * @param LintContext $context
     * @param int         $start   Where the text starts.
     * @param int         $end     Where the text ends.
     *
     * @return array{body: string, code: string, offset: int, span: Span}
     */
    private static function fromParts(LintContext $context, int $start, int $end): array
    {
        $file = $context->file;
        $span = new Span($start, $end);
        $body = $file->getText($span);
        $code = $body;
        foreach ($context->getChildren() as $part) {
            $first = $part->kind === NodeKind::StringPart ? ($file->getChildren($part)[0] ?? null) : null;
            if ($first === null || $first->kind === NodeKind::LiteralStringPart) {
                continue;
            }

            // A part, that is code. Its bytes are replaced by spaces of the same length.
            $code = \substr_replace($code, \str_repeat(' ', $part->span->length()), $part->span->start - $start, $part->span->length());
        }

        return ['body' => $body, 'code' => $code, 'offset' => $start, 'span' => $span];
    }
}
