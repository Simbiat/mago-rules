<?php

declare(strict_types=1);

// === A heredoc with no body has no parts: there is nothing to check, and nothing to report ===
$sql = <<<SQL
SQL;

// === Control: the rules still work after it ===
$control = 'SELECT NOW()'; // @expect simbiat/sql-missing-precision
