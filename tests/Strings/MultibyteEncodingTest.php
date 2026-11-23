<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\Strings;

use PHPUnit\Framework\Attributes\CoversNothing;
use Simbiat\MagoRules\Tests\RuleTestCase;

#[CoversNothing]
final class MultibyteEncodingTest extends RuleTestCase
{
    public static function ruleCodes(): array
    {
        return [
            'simbiat/multibyte-encoding' => true,
        ];
    }
}
