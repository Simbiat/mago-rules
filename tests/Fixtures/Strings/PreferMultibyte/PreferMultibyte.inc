<?php

declare(strict_types=1);

// === Single-byte form of each checked function ===
chr($codepoint); // @expect simbiat/prefer-multibyte
lcfirst($str); // @expect simbiat/prefer-multibyte
ltrim($str); // @expect simbiat/prefer-multibyte
ord($str); // @expect simbiat/prefer-multibyte
rtrim($str); // @expect simbiat/prefer-multibyte
str_pad($str, $length); // @expect simbiat/prefer-multibyte
str_split($str); // @expect simbiat/prefer-multibyte
stripos($haystack, (string) $needle); // @expect simbiat/prefer-multibyte
stristr($haystack, (string) $needle); // @expect simbiat/prefer-multibyte
strlen($str); // @expect simbiat/prefer-multibyte
strpos($haystack, (string) $needle); // @expect simbiat/prefer-multibyte
strrchr($haystack, (string) $needle); // @expect simbiat/prefer-multibyte
strripos($haystack, (string) $needle); // @expect simbiat/prefer-multibyte
strrpos($haystack, (string) $needle); // @expect simbiat/prefer-multibyte
strstr($haystack, (string) $needle); // @expect simbiat/prefer-multibyte
strtolower($str); // @expect simbiat/prefer-multibyte
strtoupper($str); // @expect simbiat/prefer-multibyte
substr($str, $start); // @expect simbiat/prefer-multibyte
substr_count($haystack, $needle); // @expect simbiat/prefer-multibyte
trim($str); // @expect simbiat/prefer-multibyte
ucfirst($str); // @expect simbiat/prefer-multibyte

// === Spelling variants and first arguments that are not plain variables ===
STRLEN($str); // @expect simbiat/prefer-multibyte
\strlen($str); // @expect simbiat/prefer-multibyte
strlen ($str); // @expect simbiat/prefer-multibyte
strlen('literal'); // @expect simbiat/prefer-multibyte
strlen(KEY); // @expect simbiat/prefer-multibyte
strlen($this->name); // @expect simbiat/prefer-multibyte
strlen(trim($str)); // @expect simbiat/prefer-multibyte simbiat/prefer-multibyte
strtolower(\strtoupper($str)); // @expect simbiat/prefer-multibyte simbiat/prefer-multibyte

// === First argument name says "binary data": must NOT be flagged ===
strlen($binary);
strlen($blob);
strlen($bytes);
strlen($raw);
strlen($hash);
strlen($digest);
strlen($checksum);
strlen($signature);
strlen($ciphertext);
strlen($plaintext);
strlen($salt);
strlen($iv);
strlen($key);
strlen($BINARY);
substr($hash, 0, 8);
trim($raw);

// === A hint can be one word of a longer name (camelCase, snake_case, plural): must NOT be flagged ===
strlen($rawBody);
strlen($user_key);
strlen($IV_BYTES);
strlen($ключ_raw);
strlen($ivBytes);
strlen($hashes);
strlen($keys);
strlen($saltValue);

// === Only an argument that STARTS with a variable counts: a hint deeper inside is not looked at ===
strlen(trim($hash)); // @expect simbiat/prefer-multibyte
strlen(f($raw)); // @expect simbiat/prefer-multibyte
strlen('$key'); // @expect simbiat/prefer-multibyte

// === A hint is a whole word only, not a part of a word: flagged ===
strlen($archive); // @expect simbiat/prefer-multibyte
strlen($private); // @expect simbiat/prefer-multibyte
strlen($active); // @expect simbiat/prefer-multibyte
strlen($monkey); // @expect simbiat/prefer-multibyte
strlen($userKeyboard); // @expect simbiat/prefer-multibyte
strlen($crawler); // @expect simbiat/prefer-multibyte
strlen($hashtag); // @expect simbiat/prefer-multibyte

// === Only the FIRST argument is looked at ===
substr($str, $hash); // @expect simbiat/prefer-multibyte
str_pad($str, $key); // @expect simbiat/prefer-multibyte

// === Already multibyte, or not the global function: must NOT be flagged ===
mb_strlen($str, 'UTF-8');
$variable_h = $obj->strlen($str);
$variable_i = $obj?->strlen($str);
$variable_j = Foo::strlen($str);
$variable_k = Foo\strlen($str);
$variable_l = namespace\strlen($str);

$anonymous = new class
{
    public function strlen(): int
    {
        return 0;
    }
};

// === Similar names / no call ===
strlen_custom($str);
strtolowercase($str);
$callable = 'strlen';

// === A call without arguments has no variable to inspect: it is flagged ===
strlen(); // @expect simbiat/prefer-multibyte
