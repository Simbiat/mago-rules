<?php

declare(strict_types=1);

// === The call is split over two lines, and the line break is inside the parentheses ===
// === The fix replaces the whole call, so the line break goes away. It has no meaning in SQL ===
// @expect-next simbiat/sql-missing-precision
$sql = 'SELECT NOW(
)';
