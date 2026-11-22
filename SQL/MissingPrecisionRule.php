<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\SQL;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Simbiat\MagoRules\Helpers\SqlDateTimeHelper;

/**
 * Enforce explicit precision argument for date and time functions in SQL literals. `0` by default.
 *
 * Auto-fixable. This was the `MissingPrecision` code of the PHPCS sniff `SQL.DateTimeFunctions`
 * and its `precision` property: it is now the constructor argument, and `bin/worker.php` sets it from `--sql-precision`.
 */
final class MissingPrecisionRule implements Rule
{
    /**
     * Code of the rule, as used in `mago.toml`, `--only`, and `@mago-ignore lint:`.
     */
    public const string CODE = 'simbiat/sql-missing-precision';

    /**
     * @param int $precision Wanted fractional-second precision. A value outside of 0-6 is brought to the nearest one.
     */
    public function __construct(private readonly int $precision = 0) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: self::CODE,
            name: 'SQL: explicit precision',
            description: 'Enforce explicit precision argument for date and time functions in SQL literals.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::LiteralString, NodeKind::InterpolatedString, NodeKind::DocumentString],
        );
    }

    public function lint(LintContext $context): void
    {
        $precision = SqlDateTimeHelper::normalisePrecision($this->precision);

        foreach (SqlDateTimeHelper::findBodies($context) as ['body' => $body, 'code' => $code, 'offset' => $offset, 'span' => $span]) {
            if (!SqlDateTimeHelper::looksLikeSql($body)) {
                continue;
            }

            $masked = SqlDateTimeHelper::mask($code);
            $function = SqlDateTimeHelper::findMissingPrecision($masked, $precision);
            if ($function === null) {
                continue;
            }

            $issue = Issue::new(
                \sprintf('%s should explicitly state (%d) fractional-second precision.', $function, $precision),
                $span,
            );
            // A `UTC_*` function is changed by PreferCurrentRule, together with its precision. Mago does not apply two edits of the same place:
            // the second one is skipped with a warning. So this rule leaves the `UTC_*` places to the other one.
            $utc_offsets = \array_column(SqlDateTimeHelper::collectUtcEdits($masked), 'offset');
            foreach (SqlDateTimeHelper::collectPrecisionEdits($masked, $precision) as $edit) {
                if (\in_array($edit['offset'], $utc_offsets, strict: true)) {
                    continue;
                }

                $issue = $issue->withEdit(TextEdit::replace(new Span($offset + $edit['offset'], $offset + $edit['offset'] + $edit['length']), $edit['replacement']));
            }

            $context->report($issue);
        }
    }
}
