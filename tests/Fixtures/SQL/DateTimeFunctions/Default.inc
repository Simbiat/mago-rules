<?php

declare(strict_types=1);

// === Precision missing or different from the configured one (default: 0) ===
$sql = 'SELECT NOW() FROM t'; // @expect simbiat/sql-missing-precision
$sql = 'SELECT NOW(3) FROM t'; // @expect simbiat/sql-missing-precision
$sql = 'SELECT CURRENT_TIMESTAMP FROM t'; // @expect simbiat/sql-missing-precision
$sql = 'SELECT CURRENT_TIME FROM t'; // @expect simbiat/sql-missing-precision
$sql = 'SELECT SYSDATE() FROM t'; // @expect simbiat/sql-missing-precision
$sql = 'SELECT CURTIME() FROM t'; // @expect simbiat/sql-missing-precision
$sql = 'SELECT now() FROM t'; // @expect simbiat/sql-missing-precision
$sql = 'CREATE TABLE t (created DATETIME NOT NULL, updated TIMESTAMP)'; // @expect simbiat/sql-missing-precision

// === UTC_* functions: both rules apply to UTC_TIMESTAMP and UTC_TIME ===
$sql = 'SELECT UTC_TIMESTAMP() FROM t'; // @expect simbiat/sql-prefer-current simbiat/sql-missing-precision
$sql = 'SELECT UTC_TIME FROM t'; // @expect simbiat/sql-prefer-current simbiat/sql-missing-precision
$sql = 'select utc_timestamp() from t'; // @expect simbiat/sql-prefer-current simbiat/sql-missing-precision
$sql = 'SELECT UTC_TIMESTAMP(0) FROM t'; // @expect simbiat/sql-prefer-current

// === UTC_DATE has no precision, so only PreferCurrent applies ===
$sql = 'SELECT UTC_DATE() FROM t'; // @expect simbiat/sql-prefer-current
$sql = 'SELECT UTC_DATE FROM t'; // @expect simbiat/sql-prefer-current

// === Several functions in one string: one result per code, all occurrences are fixed ===
$sql = 'SELECT NOW(), UTC_TIMESTAMP(), CURRENT_TIME FROM t'; // @expect simbiat/sql-prefer-current simbiat/sql-missing-precision

// === One correct and one wrong occurrence in the same string: only the wrong one is changed ===
$sql = 'SELECT NOW(0), NOW() FROM t'; // @expect simbiat/sql-missing-precision

// === A backslash before the closing quote: an escaped backslash ends nothing, an escaped quote does not end the string ===
$sql = 'SELECT NOW() \\'; // @expect simbiat/sql-missing-precision
// @expect-next simbiat/sql-missing-precision
$sql = 'SELECT NOW()
    WHERE name = \'x\'
    AND 1';

// === Multibyte characters inside a comment must not shift the changes ===
// @expect-next simbiat/sql-missing-precision
$sql = 'SELECT 1 -- ünïcödé
, NOW() FROM t';
$sql = 'SELECT /* 日本語 */ NOW() FROM t'; // @expect simbiat/sql-missing-precision

// === Every SQL keyword that opens the gate ===
$sql = 'INSERT INTO t (created) VALUES (NOW())'; // @expect simbiat/sql-missing-precision
$sql = 'UPDATE t SET updated = NOW()'; // @expect simbiat/sql-missing-precision
$sql = 'DELETE FROM t WHERE created < NOW()'; // @expect simbiat/sql-missing-precision
$sql = 'REPLACE INTO t (created) VALUES (NOW())'; // @expect simbiat/sql-missing-precision
$sql = 'ALTER TABLE t ADD created DATETIME'; // @expect simbiat/sql-missing-precision
$sql = 'WITH x AS (SELECT NOW()) SELECT * FROM x'; // @expect simbiat/sql-missing-precision
$sql = 'CALL do_something(NOW())'; // @expect simbiat/sql-missing-precision
$sql = '   SELECT NOW()'; // @expect simbiat/sql-missing-precision

// === Quote styles ===
$sql = "SELECT NOW() FROM t"; // @expect simbiat/sql-missing-precision
$sql = "SELECT NOW() FROM t WHERE id = $id"; // @expect simbiat/sql-missing-precision
$sql = 'SELECT \'x\', NOW() FROM t'; // @expect simbiat/sql-missing-precision

// === Multi-line strings: the result is reported on the first line ===
// @expect-next simbiat/sql-missing-precision
$sql = 'SELECT
    NOW()
FROM t';

// @expect-next simbiat/sql-prefer-current simbiat/sql-missing-precision
$sql = "SELECT
    UTC_TIMESTAMP(),
    id
FROM t";

// === Heredoc: the result is reported on the first line of the body ===
// @expect+2 simbiat/sql-prefer-current simbiat/sql-missing-precision
$sql = <<<SQL
    SELECT UTC_TIMESTAMP(), id
    FROM t
    SQL;

// === Comments are ignored ===
$sql = 'SELECT 1 -- NOW() is not used here';
$sql = 'SELECT 1 /* NOW() is not used here */';
// @expect-next simbiat/sql-missing-precision
$sql = 'SELECT
    /* a block comment that spans
       several lines, with UTC_TIMESTAMP() inside */
    NOW()
FROM t';

// === Already correct: must NOT be flagged ===
$sql = 'SELECT NOW(0) FROM t';
$sql = 'SELECT NOW( 0 ) FROM t';
$sql = 'SELECT CURRENT_TIMESTAMP(0) FROM t';
$sql = 'CREATE TABLE t (created DATETIME(0) NOT NULL)';
$sql = 'SELECT CURRENT_DATE FROM t';

// === The string does not start with an SQL keyword: must NOT be flagged ===
$message = 'Now it is NOW() o\'clock';
$message = 'Run SELECT NOW() to see the time';
$message = 'UTC_TIMESTAMP';
$message = '';
$message = 'S';

// === Not a whole word: must NOT be flagged ===
$sql = 'SELECT now_value, my_now, nowhere FROM t';
$sql = 'SELECT utc_timestamps FROM t';

// === Nowdoc is not checked (the sniff registers T_HEREDOC only) ===
$sql = <<<'SQL'
    SELECT NOW()
    SQL;

// === The case of the UTC_ prefix decides the case of CURRENT_, the case of the keyword is kept ===
$sql = 'SELECT UTC_date, utc_DATE FROM t'; // @expect simbiat/sql-prefer-current

// === Several wrong calls in one string ===
$sql = 'SELECT NOW(), CURTIME() FROM t'; // @expect simbiat/sql-missing-precision

// === Heredoc: a call on a later line is fixed, too ===
// @expect+2 simbiat/sql-missing-precision
$sql = <<<SQL
    SELECT id
    , NOW()
    FROM t
    SQL;
