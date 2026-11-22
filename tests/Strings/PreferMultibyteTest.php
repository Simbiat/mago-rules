<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\Strings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Simbiat\MagoRules\Helpers\CallHelper;
use Simbiat\MagoRules\Strings\PreferMultibyteRule;
use Simbiat\MagoRules\Tests\RuleTestCase;
use Simbiat\MagoRules\Tests\Support\MagoRunner;

#[CoversClass(PreferMultibyteRule::class)]
#[UsesClass(CallHelper::class)]
final class PreferMultibyteTest extends RuleTestCase
{
    public static function ruleCodes(): array
    {
        return [
            'simbiat/prefer-multibyte' => false,
        ];
    }

    public function testMessageNamesBothFunctions(): void
    {
        $issues = MagoRunner::lint(\file_get_contents(self::fixtureDirectory().'/PreferMultibyte.inc') ?: '', ['simbiat/prefer-multibyte']);
        $texts = \array_column($issues, 'message');

        self::assertContains(
            'Consider mb_strlen() instead of strlen(), unless this is deliberately operating on binary/single-byte data.',
            $texts,
        );
        self::assertContains(
            'Consider mb_str_pad() instead of str_pad(), unless this is deliberately operating on binary/single-byte data.',
            $texts,
        );
    }
}
