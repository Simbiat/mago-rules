<?php

declare(strict_types=1);

// === Fully-qualified class: flagged, same as the unqualified one ===
$a = new \TwigFunction('render', $callable, ['is_safe' => ['html']]); // @expect simbiat/twig-safe-filter-option
$b = new \TwigFilter('render', $callable, ['is_safe' => ['html']]); // @expect simbiat/twig-safe-filter-option
