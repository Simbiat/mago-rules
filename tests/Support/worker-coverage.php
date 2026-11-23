<?php

declare(strict_types=1);

/**
 * Prepares a worker process. PHP loads it before `bin/worker.php` (`auto_prepend_file`).
 *
 * Only {@see \Simbiat\MagoRules\Tests\Support\WorkerCoverage} adds this file to a command, and only when PHPUnit collects code coverage,
 * or when Infection tests a mutant. Without its settings, this file does nothing.
 *
 * Settings (`php -d name=value`):
 *  - `mago_rules.coverage_dir` The folder for the coverage data. It holds `files.json`: the list of the files to track.
 *  - `mago_rules.prepend`      Another file to load first (the value of `MAGO_WORKER_PREPEND`).
 *  - `mago_rules.mutation`     Hexadecimal JSON of `[interceptor class, interceptor file, original file, mutated file]`: the worker loads the mutated file instead of the original,
 *                              as the process of PHPUnit does. Without it, a mutation of a rule would never run, because the rules run in the worker.
 *
 * At the end of the process, the data is written as `<pid>-<random>.cov`. `WorkerCoverage::collect()` merges it into the PHPUnit coverage.
 * Nothing may be written to the standard output: Mago uses it for its protocol.
 */

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;

(static function (): void {
    $prepend = \get_cfg_var('mago_rules.prepend');
    if (\is_string($prepend) && $prepend !== '') {
        require $prepend;
    }

    $mutation = \get_cfg_var('mago_rules.mutation');
    if (\is_string($mutation) && $mutation !== '') {
        [$class, $interceptor, $original, $mutant] = \json_decode((string)\hex2bin($mutation), true, flags: \JSON_THROW_ON_ERROR);
        require_once $interceptor;
        $class::intercept($original, $mutant);
        $class::enable();
    }

    $directory = \get_cfg_var('mago_rules.coverage_dir');
    if (!\is_string($directory) || $directory === '') {
        return;
    }

    // The same lookup as in `bin/worker.php` and `tests/bootstrap.php`.
    $root = \dirname(__DIR__, 2);
    foreach ([\dirname($root, 2).'/autoload.php', $root.'/vendor/autoload.php', \dirname($root, 2).'/vendor/autoload.php'] as $candidate) {
        if (\is_file($candidate)) {
            require_once $candidate;
            break;
        }
    }

    $files = \json_decode((string)\file_get_contents($directory.'/files.json'), true, flags: \JSON_THROW_ON_ERROR);
    $filter = new Filter();
    // A copy of a file (made by a test) can stand for the real one: `copy|real`.
    $alias = \get_cfg_var('mago_rules.coverage_alias');
    $alias = \is_string($alias) && \str_contains($alias, '|') ? \explode('|', $alias, 2) : null;
    if ($alias !== null) {
        $files[] = $alias[0];
    }

    $filter->includeFiles($files);
    $coverage = new CodeCoverage(new Selector()->forLineCoverage($filter), $filter);
    // Keep in sync with WorkerCoverage::WORKER_TEST_ID.
    $coverage->start('MagoWorker');

    \register_shutdown_function(static function () use ($coverage, $directory, $alias): void {
        $coverage->stop();
        if ($alias !== null) {
            $coverage->getData(true)->renameFile($alias[0], $alias[1]);
        }

        \file_put_contents(
            $directory.'/'.\getmypid().'-'.\bin2hex(\random_bytes(4)).'.cov',
            \serialize(['data' => $coverage->getData(true), 'tests' => $coverage->getTests()]),
        );
    });
})();
