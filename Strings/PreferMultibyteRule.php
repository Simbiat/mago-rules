<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Strings;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Simbiat\MagoRules\Helpers\CallHelper;

/**
 * Suggests the `mb_*` equivalent of a single-byte string function, unless the first argument is a variable
 * whose name hints that it holds binary data (`$rawBody`, `$user_key`, `$hashes`).
 * Not auto-fixable: the replacement is a judgement call.
 */
final class PreferMultibyteRule implements Rule
{
    /**
     * Code of the rule, as used in `mago.toml`, `--only`, and `@mago-ignore lint:`.
     */
    public const string CODE = 'simbiat/prefer-multibyte';

    /**
     * Single-byte function => multibyte function.
     */
    private const array MAP = [
        'chr' => 'mb_chr',
        'lcfirst' => 'mb_lcfirst',
        'ltrim' => 'mb_ltrim',
        'ord' => 'mb_ord',
        'rtrim' => 'mb_rtrim',
        'stripos' => 'mb_stripos',
        'stristr' => 'mb_stristr',
        'strlen' => 'mb_strlen',
        'strpos' => 'mb_strpos',
        'strrchr' => 'mb_strrchr',
        'strripos' => 'mb_strripos',
        'strrpos' => 'mb_strrpos',
        'strstr' => 'mb_strstr',
        'strtolower' => 'mb_strtolower',
        'strtoupper' => 'mb_strtoupper',
        'str_pad' => 'mb_str_pad',
        'str_split' => 'mb_str_split',
        'substr' => 'mb_substr',
        'substr_count' => 'mb_substr_count',
        'trim' => 'mb_trim',
        'ucfirst' => 'mb_ucfirst',
    ];

    /**
     * Words in a variable name, that hint at binary data.
     */
    private const array NAME_HINTS = [
        'binary',
        'blob',
        'bytes',
        'raw',
        'hash',
        'digest',
        'checksum',
        'signature',
        'ciphertext',
        'plaintext',
        'salt',
        'iv',
        'key',
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: self::CODE,
            name: 'Prefer multibyte',
            description: 'Recommends multibyte (mb_*) functions instead of single-byte functions, unless the data looks binary.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    public function lint(LintContext $context): void
    {
        $found = CallHelper::findCall($context, self::MAP);
        if ($found === null) {
            return;
        }

        ['name' => $function_name, 'call' => $call] = $found;
        if (self::firstArgumentLooksBinary($context, $call->arguments[0] ?? null)) {
            return;
        }

        $context->report(
            Issue::new(
                \sprintf('Consider %s() instead of %s(), unless this is deliberately operating on binary/single-byte data.', self::MAP[$function_name], $function_name),
                $context->node->span,
            ),
        );
    }

    private static function firstArgumentLooksBinary(LintContext $context, ?\Mago\Sdk\Syntax\CallArgument $argument): bool
    {
        if ($argument === null) {
            return false;
        }

        // Only an argument that starts with a variable counts: `$rawBody`, `$rawBody['x']`, `$rawBody . 'x'`.
        // A named argument (`string: $rawBody`) starts with its name, so it does not count, same as before.
        if (\preg_match('/^\$(\w+)/u', $context->file->getText($argument->node), $matches) !== 1) {
            return false;
        }

        // Split `$rawBody`, `$user_key` or `$IV_BYTES` into words, and compare whole words only.
        // A substring match would also catch `$archive` (iv), `$monkey` (key) or `$crawler` (raw).
        // The patterns are constant and the subject is a variable name, so preg_replace() cannot fail here. The fallback is for static analysis only.
        $name = \preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $matches[1]) ?? '';

        // The hints are ASCII words, so only ASCII letters need to change case: `strtolower()` is correct and `mb_strtolower()` is not needed.
        // phpcs:ignore Simbiat.Strings.PreferMultibyte -- The compared words are ASCII.
        foreach (\explode('_', \strtolower($name)) as $word) {
            if (\in_array($word, self::NAME_HINTS, strict: true) || \in_array((string)\preg_replace('/(?:es|s)$/', '', $word), self::NAME_HINTS, strict: true)) {
                return true;
            }
        }

        return false;
    }
}
