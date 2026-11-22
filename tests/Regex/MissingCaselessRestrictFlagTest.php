<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\Regex;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Simbiat\MagoRules\Helpers\CallHelper;
use Simbiat\MagoRules\Helpers\PregCallHelper;
use Simbiat\MagoRules\Helpers\RegexLiteralHelper;
use Simbiat\MagoRules\Regex\MissingCaselessRestrictFlagRule;
use Simbiat\MagoRules\Tests\RuleTestCase;

#[CoversClass(MissingCaselessRestrictFlagRule::class)]
#[UsesClass(CallHelper::class)]
#[UsesClass(PregCallHelper::class)]
#[UsesClass(RegexLiteralHelper::class)]
final class MissingCaselessRestrictFlagTest extends RuleTestCase
{
    public static function ruleCodes(): array
    {
        return [
            'simbiat/missing-caseless-restrict-flag' => true,
        ];
    }
}
