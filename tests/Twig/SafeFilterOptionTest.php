<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\Twig;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Simbiat\MagoRules\Tests\RuleTestCase;
use Simbiat\MagoRules\Tests\Support\MagoRunner;

#[CoversNothing]
final class SafeFilterOptionTest extends RuleTestCase
{
    public static function ruleCodes(): array
    {
        return [
            'simbiat/twig-safe-filter-option' => false,
        ];
    }

    /**
     * Code that is cut off in the middle of `new TwigFunction(...)`. A fixture cannot hold it, because it is not valid PHP.
     * Mago cannot make a syntax tree of such code, so no rule runs for it. This is a check that nothing breaks.
     *
     * @return array<string, array{string}>
     */
    public static function incompleteCodeProvider(): array
    {
        return [
            'ends after new' => ['<?php new'],
            'ends after the class name' => ['<?php new TwigFunction'],
            'ends after the opening parenthesis' => ['<?php new TwigFunction('],
            'the parenthesis is never closed' => ["<?php new TwigFunction('a', \$c, ['is_safe' => ['html']]"],
        ];
    }

    #[DataProvider('incompleteCodeProvider')]
    public function testIncompleteCodeIsIgnored(string $code): void
    {
        $issues = MagoRunner::lint($code, ['simbiat/twig-safe-filter-option']);

        self::assertSame([], \array_values(\array_filter($issues, static fn(array $issue): bool => $issue['code'] === 'simbiat/twig-safe-filter-option')));
    }

    /**
     * A comment between `new` and the class name does not hide the class: Mago knows what a comment is, the token based sniff did not.
     * A fixture cannot hold it: the comment must stand directly after `new`, and the formatting tools of the project would add a space.
     */
    public function testCommentAfterNewDoesNotHideTheClass(): void
    {
        $issues = MagoRunner::lint("<?php\nnew/* c */TwigFunction('a', \$c, ['is_safe' => ['html']]);\n", ['simbiat/twig-safe-filter-option']);

        self::assertSame([2], \array_column($issues, 'line'));
    }
}
