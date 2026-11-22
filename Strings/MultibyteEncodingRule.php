<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Strings;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;
use Simbiat\MagoRules\Helpers\CallHelper;

/**
 * Flags `mb_*()` calls that rely on the default/internal encoding, and adds
 * `encoding: 'UTF-8'` as a named argument. Auto-fixable.
 *
 * The map holds the position of the encoding parameter (1-based): a call with
 * that many arguments (or more) already has it.
 */
final class MultibyteEncodingRule implements Rule
{
    /**
     * Code of the rule, as used in `mago.toml`, `--only`, and `@mago-ignore lint:`.
     */
    public const string CODE = 'simbiat/multibyte-encoding';

    /**
     * `mb_convert_encoding()` has no `$encoding` parameter: its encoding parameters are `$to_encoding` and `$from_encoding`.
     * The missing one is `from_encoding`, which is why the fix names it explicitly.
     */
    private const array ENCODING_PARAM_NAMES = [
        'mb_convert_encoding' => 'from_encoding',
    ];

    /**
     * Function name => the position of the encoding parameter.
     */
    private const array FUNCTIONS = [
        'mb_check_encoding' => 2,
        'mb_chr' => 2,
        'mb_convert_case' => 3,
        'mb_convert_encoding' => 3,
        'mb_convert_kana' => 3,
        'mb_decode_numericentity' => 3,
        'mb_encode_numericentity' => 3,
        'mb_http_output' => 1,
        'mb_lcfirst' => 2,
        'mb_ltrim' => 3,
        'mb_ord' => 2,
        'mb_rtrim' => 3,
        'mb_scrub' => 2,
        'mb_strcut' => 4,
        'mb_strimwidth' => 5,
        'mb_stripos' => 4,
        'mb_stristr' => 4,
        'mb_strlen' => 2,
        'mb_strpos' => 4,
        'mb_strrchr' => 4,
        'mb_strrichr' => 4,
        'mb_strripos' => 4,
        'mb_strrpos' => 4,
        'mb_strstr' => 4,
        'mb_strtolower' => 2,
        'mb_strtoupper' => 2,
        'mb_strwidth' => 2,
        'mb_str_pad' => 5,
        'mb_str_split' => 3,
        'mb_substr' => 4,
        'mb_substr_count' => 3,
        'mb_trim' => 3,
        'mb_ucfirst' => 2,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: self::CODE,
            name: 'Multibyte encoding',
            description: 'Highlights mb_* calls missing an explicit encoding. UTF-8 is recommended in most modern environments.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    public function lint(LintContext $context): void
    {
        $found = CallHelper::findCall($context, self::FUNCTIONS);
        if ($found === null) {
            return;
        }

        ['name' => $function_name, 'call' => $call] = $found;

        // Encoding slot already reached positionally, or supplied by name.
        if (
            \count($call->arguments) >= self::FUNCTIONS[$function_name]
            || CallHelper::hasNamedEncoding($call)
        ) {
            return;
        }

        $param_name = self::ENCODING_PARAM_NAMES[$function_name] ?? 'encoding';

        // Named argument: always valid here regardless of how many
        // optional parameters sit between the last given argument and
        // the encoding slot, so this never needs to know the exact
        // position — only that the slot is not yet filled (checked above).
        // A trailing comma (`f($a, $b,)`) already separates the new argument from the last one.
        $prefix = $call->arguments !== [] && !CallHelper::hasTrailingComma($context->file, $call)
            ? ', '
            : '';

        $context->report(
            Issue::new(
                \sprintf('Call to %s() should state the encoding explicitly instead of relying on the internal/default one.', $function_name),
                $context->node->span,
            )->withEdit(TextEdit::insert(CallHelper::closeParenthesisOffset($call), $prefix.$param_name.": 'UTF-8'")),
        );
    }
}
