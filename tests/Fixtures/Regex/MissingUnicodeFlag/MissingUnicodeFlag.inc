<?php

declare(strict_types=1);

// === Missing Unicode flag: every checked function ===
preg_filter('/foo/', $replacement, $subject); // @expect simbiat/missing-unicode-flag
preg_grep('/foo/', $array); // @expect simbiat/missing-unicode-flag
preg_match('/foo/', $subject, $matches); // @expect simbiat/missing-unicode-flag
preg_match_all('/foo/', $subject, $matches); // @expect simbiat/missing-unicode-flag
preg_replace('/foo/', $replacement, $subject); // @expect simbiat/missing-unicode-flag
preg_replace_callback('/foo/', $callback, $subject); // @expect simbiat/missing-unicode-flag
preg_split('/foo/', $subject); // @expect simbiat/missing-unicode-flag

// === Missing Unicode flag, with other flags present ===
preg_match('/foo/i', $subject, $matches); // @expect simbiat/missing-unicode-flag
preg_match('/foo/ims', $subject, $matches); // @expect simbiat/missing-unicode-flag
preg_match('/foo/ir', $subject, $matches); // @expect simbiat/missing-unicode-flag

// === Other delimiters and quote styles ===
preg_match('#foo#', $subject); // @expect simbiat/missing-unicode-flag
preg_match('~foo~i', $subject); // @expect simbiat/missing-unicode-flag
preg_match("/foo/", $subject); // @expect simbiat/missing-unicode-flag
preg_match('/a\/b/', $subject); // @expect simbiat/missing-unicode-flag
preg_match('/a\/b/i', $subject); // @expect simbiat/missing-unicode-flag
PREG_MATCH('/foo/', $subject); // @expect simbiat/missing-unicode-flag

// === Multi-line call: the result is reported on the line of the pattern ===
preg_match(
    '/foo/', // @expect simbiat/missing-unicode-flag
    $subject,
);

// === Unicode flag present: must NOT be flagged ===
preg_match('/foo/u', $subject, $matches);
preg_match('/foo/iu', $subject, $matches);
preg_match('/foo/ui', $subject, $matches);
preg_match('/foo/uir', $subject, $matches);
preg_match('#foo#u', $subject);
preg_match("/foo/u", $subject);
preg_match('/a\/b/u', $subject);

// === Not a literal pattern, or an incomplete call: must NOT be flagged ===
preg_match($pattern, $subject, $matches);
preg_match(self::PATTERN, $subject);
preg_match('/foo/');
preg_replace_callback_array(['/foo/' => $callback], $subject);
preg_quote('/foo/');
preg_last_error();

// === Bracket-style delimiters are not understood by the sniff (known limitation of RegexLiteralHelper) ===
preg_match('(foo)', $subject);
preg_match('{foo}i', $subject);

// === Not the global function: must NOT be flagged ===
Foo\preg_match('/foo/', $subject);
$variable = 'preg_match';
