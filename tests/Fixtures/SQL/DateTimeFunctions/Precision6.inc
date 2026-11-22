<?php

declare(strict_types=1);

// @property precision=6

// === Configured precision is 6 ===
$sql = 'SELECT NOW() FROM t'; // @expect simbiat/sql-missing-precision
$sql = 'SELECT NOW(0) FROM t'; // @expect simbiat/sql-missing-precision
$sql = 'SELECT NOW(3) FROM t'; // @expect simbiat/sql-missing-precision
$sql = 'SELECT UTC_TIMESTAMP() FROM t'; // @expect simbiat/sql-prefer-current simbiat/sql-missing-precision
$sql = 'SELECT UTC_TIMESTAMP(6) FROM t'; // @expect simbiat/sql-prefer-current
$sql = 'SELECT UTC_DATE() FROM t'; // @expect simbiat/sql-prefer-current
$sql = 'CREATE TABLE t (created DATETIME NOT NULL)'; // @expect simbiat/sql-missing-precision
// A correct call keeps its own spacing, and only the wrong call in the same string is changed.
$sql = 'SELECT NOW( 6 ), CURTIME() FROM t'; // @expect simbiat/sql-missing-precision

// === Already correct: must NOT be flagged ===
$sql = 'SELECT NOW(6) FROM t';
$sql = 'SELECT CURRENT_TIMESTAMP(6) FROM t';
$sql = 'SELECT CURRENT_DATE FROM t';
