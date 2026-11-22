<?php

declare(strict_types=1);

// === is_safe in the options array: flagged ===
$a = new TwigFunction('render', $callable, ['is_safe' => ['html']]); // @expect simbiat/twig-safe-filter-option
$b = new TwigFilter('render', $callable, ['is_safe' => ['html']]); // @expect simbiat/twig-safe-filter-option
$e = new TwigFunction('render', $callable, ["is_safe" => ['html']]); // @expect simbiat/twig-safe-filter-option
$f = new TwigFunction('render', $callable, ['needs_environment' => true, 'is_safe' => ['html']]); // @expect simbiat/twig-safe-filter-option
$g = new TwigFunction('render', $callable, options: ['is_safe' => ['html']]); // @expect simbiat/twig-safe-filter-option
$g2 = new TwigFunction('is_safe'); // @expect simbiat/twig-safe-filter-option
$h = new TwigFunction( // @expect simbiat/twig-safe-filter-option
    'render',
    $callable,
    [
        'is_safe' => ['html'],
    ],
);

// === One result per call, even with the option repeated ===
$i = new TwigFunction('render', $callable, ['is_safe' => ['html'], 'is_safe_callback' => 'x', 'is_safe' => ['js']]); // @expect simbiat/twig-safe-filter-option

// === Inside a nested call: the inner `new` is judged on its own ===
$j = array_map(static fn($name) => new TwigFilter($name, $callable, ['is_safe' => ['html']]), $names); // @expect simbiat/twig-safe-filter-option

// === No is_safe option: must NOT be flagged ===
$k = new TwigFunction('render', $callable);
$l = new TwigFilter('render', $callable, ['needs_environment' => true]);
$m = new TwigFunction('render', $callable, ['is_safe_callback' => $callback]);

// === Other classes, or namespaced Twig classes: must NOT be flagged ===
$o = new SomethingElse('render', $callable, ['is_safe' => ['html']]);
$p = new Twig\TwigFunction('render', $callable, ['is_safe' => ['html']]);
$q = new \Twig\TwigFilter('render', $callable, ['is_safe' => ['html']]);
$r = new TwigFunction;
$s = new TwigFunction();

// === Only `new` counts ===
TwigFunction::create('render', $callable, ['is_safe' => ['html']]);
$t = ['is_safe' => ['html']];
