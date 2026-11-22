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
 * Prefer `CURRENT_*` functions in SQL literals instead of `UTC_*` ones.
 * `UTC_*` bypasses whatever timezone you are using on the server, and unless it is already `UTC`, can result in time-related bugs.
 *
 * Auto-fixable. This was the `PreferCurrent` code of the PHPCS sniff `SQL.DateTimeFunctions`.
 * Mago has one code per rule, so the sniff is split in two rules: this one and {@see MissingPrecisionRule}.
 */
final class PreferCurrentRule implements Rule
{
    /**
     * Code of the rule, as used in `mago.toml`, `--only`, and `@mago-ignore lint:`.
     */
    public const string CODE = 'simbiat/sql-prefer-current';

    /**
     * @param int $precision The same precision as the one of {@see MissingPrecisionRule}.
     *                       It is used to make one edit of `UTC_TIMESTAMP` => `CURRENT_TIMESTAMP(0)`, where both rules want to change the same place.
     */
    public function __construct(private readonly int $precision = 0) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: self::CODE,
            name: 'SQL: prefer CURRENT_*',
            description: 'Prefer CURRENT_* functions in SQL literals instead of UTC_* ones.',
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
            $utc = SqlDateTimeHelper::findUtc($masked);
            if ($utc === null) {
                continue;
            }

            $edits = SqlDateTimeHelper::mergeUtcWithPrecision(
                SqlDateTimeHelper::collectUtcEdits($masked),
                SqlDateTimeHelper::collectPrecisionEdits($masked, $precision),
            );

            $issue = Issue::new(
                \sprintf('Use CURRENT_%s instead of %s%s.', $utc['suffix'], $utc['prefix'], $utc['suffix']),
                $span,
            );
            foreach ($edits as $edit) {
                $issue = $issue->withEdit(TextEdit::replace(new Span($offset + $edit['offset'], $offset + $edit['offset'] + $edit['length']), $edit['replacement']));
            }

            $context->report($issue);
        }
    }
}
