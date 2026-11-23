<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Twig;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Flags `new TwigFunction(...)` / `new TwigFilter(...)` that have an `'is_safe'` string anywhere in the arguments.
 * That option turns off Twig's auto-escaping for the output, so the callable has to sanitise it itself.
 */
final class SafeFilterOptionRule implements Rule
{
    /**
     * Code of the rule, as used in `mago.toml`, `--only`, and `@mago-ignore lint:`.
     */
    public const string CODE = 'simbiat/twig-safe-filter-option';

    /**
     * Class names to check. Only the short names, with or without a leading backslash, same as before.
     */
    private const array CLASSES = ['TwigFunction', 'TwigFilter'];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: self::CODE,
            name: 'Twig is_safe option',
            description: 'Highlights new TwigFunction(...) / new TwigFilter(...) with the is_safe flag, which turns off auto-escaping.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Instantiation],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $class_name = null;
        $arguments = null;
        foreach ($context->getChildren() as $child) {
            if ($child->kind === NodeKind::Expression) {
                // `new \TwigFunction`: strip the leading backslash.
                // A longer name (`\Twig\TwigFunction`) will not match the list, so it stays ignored.
                $class_name = \ltrim($file->getText($child), '\\');
            } elseif ($child->kind === NodeKind::ArgumentList) {
                $arguments = $child;
            }
        }

        if ($class_name === null || $arguments === null || !\in_array($class_name, self::CLASSES, strict: true)) {
            return;
        }

        // Any string literal inside the parentheses counts, however deeply it is nested: `['is_safe' => ['html']]`.
        foreach ($file->getDescendants($arguments, NodeKind::LiteralString) as $literal) {
            if (\mb_trim($file->getText($literal), "'\"", 'UTF-8') === 'is_safe') {
                $context->report(
                    Issue::new(
                        \sprintf("'is_safe' on %s bypasses Twig's auto-escaping — confirm the callable's output is actually sanitised.", $class_name),
                        $context->node->span,
                    ),
                );

                return;
            }
        }
    }
}
