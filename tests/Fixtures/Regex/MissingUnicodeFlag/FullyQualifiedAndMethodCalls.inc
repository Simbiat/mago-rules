<?php

declare(strict_types=1);

// === Fully-qualified call: flagged, same as the unqualified one ===
\preg_match('/foo/', $subject, $matches); // @expect simbiat/missing-unicode-flag
\preg_replace('/foo/i', $replacement, $subject); // @expect simbiat/missing-unicode-flag

// === Method / static calls named like a preg_* function: ignored ===
$object->preg_match('/foo/', $subject);
Foo::preg_match('/foo/', $subject);
