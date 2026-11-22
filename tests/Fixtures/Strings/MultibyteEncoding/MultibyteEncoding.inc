<?php

declare(strict_types=1);

// === Every checked function, one argument short of the encoding slot ===
mb_check_encoding($value); // @expect simbiat/multibyte-encoding
mb_chr($codepoint); // @expect simbiat/multibyte-encoding
mb_convert_case($str, \MB_CASE_TITLE); // @expect simbiat/multibyte-encoding
mb_convert_encoding($str, 'UTF-8'); // @expect simbiat/multibyte-encoding
mb_convert_kana($str, 'KV'); // @expect simbiat/multibyte-encoding
mb_decode_numericentity($str, $map); // @expect simbiat/multibyte-encoding
mb_encode_numericentity($str, $map); // @expect simbiat/multibyte-encoding
mb_http_output(); // @expect simbiat/multibyte-encoding
mb_lcfirst($str); // @expect simbiat/multibyte-encoding
mb_ltrim($str, $characters); // @expect simbiat/multibyte-encoding
mb_ord($str); // @expect simbiat/multibyte-encoding
mb_rtrim($str, $characters); // @expect simbiat/multibyte-encoding
mb_scrub($str); // @expect simbiat/multibyte-encoding
mb_str_pad($str, $length, ' ', \STR_PAD_RIGHT); // @expect simbiat/multibyte-encoding
mb_str_split($str, $length); // @expect simbiat/multibyte-encoding
mb_strcut($str, $start, $length); // @expect simbiat/multibyte-encoding
mb_strimwidth($str, $start, $width, '...'); // @expect simbiat/multibyte-encoding
mb_stripos($haystack, $needle, $offset); // @expect simbiat/multibyte-encoding
mb_stristr($haystack, $needle, before_needle: false); // @expect simbiat/multibyte-encoding
mb_strlen($str); // @expect simbiat/multibyte-encoding
mb_strpos($haystack, $needle, $offset); // @expect simbiat/multibyte-encoding
mb_strrchr($haystack, $needle, before_needle: false); // @expect simbiat/multibyte-encoding
mb_strrichr($haystack, $needle, before_needle: false); // @expect simbiat/multibyte-encoding
mb_strripos($haystack, $needle, $offset); // @expect simbiat/multibyte-encoding
mb_strrpos($haystack, $needle, $offset); // @expect simbiat/multibyte-encoding
mb_strstr($haystack, $needle, before_needle: false); // @expect simbiat/multibyte-encoding
mb_strtolower($str); // @expect simbiat/multibyte-encoding
mb_strtoupper($str); // @expect simbiat/multibyte-encoding
mb_strwidth($str); // @expect simbiat/multibyte-encoding
mb_substr($str, $start, $length); // @expect simbiat/multibyte-encoding
mb_substr_count($haystack, $needle); // @expect simbiat/multibyte-encoding
mb_trim($str, $characters); // @expect simbiat/multibyte-encoding
mb_ucfirst($str); // @expect simbiat/multibyte-encoding

// === Spelling variants of the function name are still the same function ===
MB_STRLEN($str); // @expect simbiat/multibyte-encoding
\mb_strlen($str); // @expect simbiat/multibyte-encoding
mb_strlen ($str); // @expect simbiat/multibyte-encoding

// === Multi-line call (without trailing comma; see TrailingComma.inc for the other form) ===
mb_substr( // @expect simbiat/multibyte-encoding
    $str,
    0,
    5
);

// === Nested calls: each call is judged on its own ===
mb_strtoupper(mb_substr($str, 0, 1, 'UTF-8')); // @expect simbiat/multibyte-encoding
mb_strtoupper(mb_substr($str, 0, 1), 'UTF-8'); // @expect simbiat/multibyte-encoding

// === Encoding already explicit: must NOT be flagged ===
$variable_a = mb_strlen($str, 'UTF-8');
$variable_b = mb_substr($str, 0, 5, encoding: 'UTF-8');
$variable_c = mb_substr($str, 0, 5, encoding : 'UTF-8');
$variable_x = mb_strlen($str, $variable_encoding);
$variable_y = mb_strlen($str, 'ISO-8859-1');
$variable_z = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
mb_http_output('UTF-8');
mb_str_pad($str, 10, ' ', \STR_PAD_RIGHT, 'UTF-8');

// === Not the global function: must NOT be flagged ===
$variable_h = $obj->mb_strlen($str);
$variable_i = $obj?->mb_strlen($str);
$variable_j = Foo::mb_strlen($str);
$variable_k = Foo\mb_strlen($str);
$variable_l = namespace\mb_strlen($str);

$anonymous = new class
{
    public function mb_strlen(string $str): int
    {
        return 0;
    }
};

// === Unrelated function with a similar name ===
mb_internal_encoding('UTF-8');
mb_strlen_custom($str);

// === Function name without a call ===
$callable = 'mb_strlen';
