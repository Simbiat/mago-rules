<?php

declare(strict_types=1);

namespace Simbiat\MagoRules;

use Mago\Sdk\Extension;
use Simbiat\MagoRules\PHP\ExitCodeRule;
use Simbiat\MagoRules\PHP\PreferWhiteStringRule;
use Simbiat\MagoRules\Regex\MissingCaselessRestrictFlagRule;
use Simbiat\MagoRules\Regex\MissingUnicodeFlagRule;
use Simbiat\MagoRules\SQL\MissingPrecisionRule;
use Simbiat\MagoRules\SQL\PreferCurrentRule;
use Simbiat\MagoRules\Strings\MultibyteEncodingRule;
use Simbiat\MagoRules\Strings\PreferMultibyteRule;
use Simbiat\MagoRules\Twig\SafeFilterOptionRule;

/**
 * The list of the rules of this package. It is what `ruleset.xml` was for PHPCS.
 *
 * Whether a rule is on, and its level, are set in `mago.toml` under `[linter.rules]`, by the code of the rule.
 * The settings of a rule itself (there is only one: the precision of the SQL functions) are arguments of {@see self::create()},
 * and `bin/worker.php` fills them from its command line.
 */
final class MagoExtension
{
    private function __construct() {}

    /**
     * @param int $sqlPrecision Wanted fractional-second precision for the SQL date/time functions (0-6). It was the `precision` property of `Simbiat.SQL.DateTimeFunctions`.
     *
     * @return Extension
     */
    public static function create(int $sqlPrecision = 0): Extension
    {
        return new Extension(
            identifier: 'simbiat/mago-rules',
            name: 'Simbiat custom rules',
            version: '1.0.0',
            linterRules: [
                new MultibyteEncodingRule(),
                new PreferMultibyteRule(),
                new ExitCodeRule(),
                new PreferWhiteStringRule(),
                new SafeFilterOptionRule(),
                new PreferCurrentRule($sqlPrecision),
                new MissingPrecisionRule($sqlPrecision),
                new MissingUnicodeFlagRule(),
                new MissingCaselessRestrictFlagRule(),
            ],
        );
    }
}
