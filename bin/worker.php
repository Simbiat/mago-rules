<?php

declare(strict_types=1);

/**
 * The entry point that Mago starts as an extension host. Add it to `mago.toml`:
 *
 * [extension-hosts.simbiat]
 * command = ["php", "vendor/simbiat/mago-rules/bin/worker.php", "--sql-precision=0"]
 *
 * Options:
 *   --sql-precision=N  Wanted fractional-second precision (0-6) for `simbiat/sql-missing-precision`. Default: 0.
 *
 * Mago reads the standard output of this process as its protocol stream, so nothing else may be written there.
 * Write to the standard error, if you need to print something.
 */

use Mago\Sdk\Worker;
use Simbiat\MagoRules\MagoExtension;

// The package can be a dependency (vendor/simbiat/mago-rules/bin), or a folder of the project (packages/MagoRules/bin).
$autoload = null;
foreach ([\dirname(__DIR__, 3).'/autoload.php', \dirname(__DIR__).'/vendor/autoload.php', \dirname(__DIR__, 3).'/vendor/autoload.php'] as $candidate) {
    if (\is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}

if ($autoload === null) {
    \fwrite(\STDERR, "Cannot find the Composer autoloader. Run \"composer install\".\n");

    exit(1);
}

require $autoload;

$options = \getopt('', ['sql-precision:']);
$precision = $options['sql-precision'] ?? '0';
if (!\is_string($precision) || \filter_var($precision, \FILTER_VALIDATE_INT) === false) {
    \fwrite(\STDERR, "--sql-precision must be an integer.\n");

    exit(1);
}

new Worker(MagoExtension::create((int)$precision))->run();
