<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\Strings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Simbiat\MagoRules\Helpers\CallHelper;
use Simbiat\MagoRules\Strings\MultibyteEncodingRule;
use Simbiat\MagoRules\Tests\RuleTestCase;

#[CoversClass(MultibyteEncodingRule::class)]
#[UsesClass(CallHelper::class)]
final class MultibyteEncodingTest extends RuleTestCase
{
    public static function ruleCodes(): array
    {
        return [
            'simbiat/multibyte-encoding' => true,
        ];
    }
}
