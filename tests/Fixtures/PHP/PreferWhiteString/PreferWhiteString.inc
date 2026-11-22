<?php

declare(strict_types=1);

// === Whitespace-only check written as preg_match: flagged ===
$a = preg_match('/^\s*$/', $key) === 1; // @expect simbiat/prefer-white-string
$b = preg_match('/^\s*$/u', $key) === 1; // @expect simbiat/prefer-white-string
$c = preg_match("/^\s*$/ui", $key) === 1; // @expect simbiat/prefer-white-string
$d = preg_match('/^\s*$/', $key) !== 1; // @expect simbiat/prefer-white-string
$e = PREG_MATCH('/^\s*$/', $key) === 1; // @expect simbiat/prefer-white-string
$f = preg_match('/^\s*$/', $key, $matches) === 1; // @expect simbiat/prefer-white-string
$g = preg_match('/^\s*$/u', $this->getValue()) === 1; // @expect simbiat/prefer-white-string
// @expect-next simbiat/prefer-white-string
$h = preg_match(
        '/^\s*$/u',
        $key,
    ) === 1;

if (preg_match('/^\s*$/u', $value) === 1) { // @expect simbiat/prefer-white-string
    return;
}

// === Every allowed flag is accepted ===
$i = preg_match('/^\s*$/imsxADSUXJur', $key) === 1; // @expect simbiat/prefer-white-string

// === No whitespace between the tokens ===
$y = preg_match('/^\s*$/u',$key)===1; // @expect simbiat/prefer-white-string
$z = preg_match('/^\s*$/u',$key,$matches)!==1; // @expect simbiat/prefer-white-string

// === /^\s+$/ is accepted as the pattern core too (see the docblock of the sniff) ===
$x = preg_match('/^\s+$/u', $key) === 1; // @expect simbiat/prefer-white-string

// === Different pattern: must NOT be flagged ===
$j = preg_match('/^\d*$/', $key) === 1;
$k = preg_match('/^\s*$/n', $key) === 1;
$l = preg_match('/\s/', $key) === 1;
$m = preg_match('/^\s*/', $key) === 1;
$n = preg_match($pattern, $key) === 1;

// === Another preg function: must NOT be flagged ===
$n2 = preg_replace('/^\s*$/', ' ', $key) === 1;

// === Different comparison: must NOT be flagged ===
$o = preg_match('/^\s*$/', $key) == 1;
$p = preg_match('/^\s*$/', $key) === 0;
$q = preg_match('/^\s*$/', $key) > 0;
$r = preg_match('/^\s*$/', $key);
$s = 1 === preg_match('/^\s*$/', $key);

// === Incomplete call: must NOT be flagged ===
$t = preg_match('/^\s*$/') === 1;
$t = preg_match('/^\s*$/', ) === 1;
$t = preg_match; // the name is not followed by a parenthesis

// === Not the global function: must NOT be flagged ===
$u = $object->preg_match('/^\s*$/', $key) === 1;
$v = Foo::preg_match('/^\s*$/', $key) === 1;
$w = Foo\preg_match('/^\s*$/', $key) === 1;
