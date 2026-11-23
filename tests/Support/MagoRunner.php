<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\Support;

/**
 * Runs the real `mago` binary with this package as an extension, on one piece of code, and gives back what Mago says.
 *
 * Rules run in a separate worker process, which Mago starts. There is no way to call a rule from a test directly,
 * and a fake of the Mago protocol would test the fake, so the tests use the real thing: the same command that you run in the project.
 */
final class MagoRunner
{
    /**
     * A limit for the loop of the fixer: more passes than this means that fixes keep changing each other's results.
     */
    private const int MAX_FIX_PASSES = 5;

    /**
     * The root of the package.
     */
    public static function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * Find the `mago` binary: the `MAGO_BIN` environment variable, then Composer's `vendor/bin/mago` (the package or the parent project), then `PATH`.
     *
     * @return string
     */
    public static function binary(): string
    {
        $from_environment = \getenv('MAGO_BIN');
        if (\is_string($from_environment) && $from_environment !== '') {
            return $from_environment;
        }

        foreach ([self::root().'/vendor/bin/mago', \dirname(self::root(), 2).'/vendor/bin/mago'] as $candidate) {
            if (\is_file($candidate)) {
                return $candidate;
            }
        }

        return 'mago';
    }

    /**
     * Run the rules of the given codes on the code.
     *
     * @param string       $code      The content of a PHP file.
     * @param list<string> $ruleCodes Codes of the rules, like `simbiat/exit-code`. Only these rules run.
     * @param int          $precision The `--sql-precision` option of the worker.
     *
     * @return list<array{code: string, line: int, fixable: bool, message: string, level: string}> Sorted by line. The line is 1-based.
     */
    public static function lint(string $code, array $ruleCodes, int $precision = 0): array
    {
        $directory = self::prepare($code, $precision);
        try {
            $result = self::mago($directory, self::onlyArguments($ruleCodes, ['--reporting-format', 'json']));

            return self::parseIssues($result['stdout'], $result['stderr']);
        } finally {
            self::remove($directory);
        }
    }

    /**
     * Apply the fixes of the rules of the given codes, until the file stops changing. One run of `mago lint --fix` is one pass.
     *
     * @param string       $code
     * @param list<string> $ruleCodes
     * @param int          $precision
     *
     * @return array{contents: string, passes: int, converged: bool}
     */
    public static function fix(string $code, array $ruleCodes, int $precision = 0): array
    {
        $directory = self::prepare($code, $precision);
        try {
            $passes = 0;
            $contents = $code;
            while ($passes < self::MAX_FIX_PASSES) {
                self::mago($directory, self::onlyArguments($ruleCodes, ['--fix']));
                $new_contents = (string)\file_get_contents($directory.'/fixture.php');
                if ($new_contents === $contents) {
                    return ['contents' => $contents, 'passes' => $passes, 'converged' => true];
                }

                $contents = $new_contents;
                ++$passes;
            }

            return ['contents' => $contents, 'passes' => $passes, 'converged' => false];
        } finally {
            self::remove($directory);
        }
    }

    /**
     * The output of `mago extension list` for a project that uses this package: what Mago itself knows about the extension.
     *
     * @return string
     */
    public static function extensionList(): string
    {
        $directory = self::prepare('<?php', 0);
        try {
            $result = self::mago($directory, ['extension', 'list']);
            if ($result['code'] !== 0) {
                throw new \RuntimeException('"mago extension list" failed. Output: '.$result['stdout'].' Errors: '.$result['stderr']);
            }

            return $result['stdout'];
        } finally {
            self::remove($directory);
        }
    }

    /**
     * @param list<string> $ruleCodes
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private static function onlyArguments(array $ruleCodes, array $arguments): array
    {
        $result = ['lint'];
        foreach ($ruleCodes as $rule_code) {
            \array_push($result, '--only', $rule_code);
        }

        return \array_merge($result, $arguments);
    }

    /**
     * Make a folder with the code and a `mago.toml` that runs the worker of this package.
     */
    private static function prepare(string $code, int $precision): string
    {
        $directory = \sys_get_temp_dir().'/mago-rules-'.\bin2hex(\random_bytes(6));
        if (!\mkdir($directory, 0o700) && !\is_dir($directory)) {
            throw new \RuntimeException('Cannot create '.$directory);
        }

        \file_put_contents($directory.'/fixture.php', $code);
        $worker = \str_replace('\\', '/', self::root().'/bin/worker.php');
        // `MAGO_WORKER_PREPEND` is for environments, that need something before the worker starts. Usually there is none.
        // When PHPUnit collects code coverage, the worker also gets the settings to collect its own: PHPUnit cannot see its process.
        $command = ['php', ...WorkerCoverage::phpArguments(), $worker, '--sql-precision='.$precision];

        \file_put_contents(
            $directory.'/mago.toml',
            "php-version = \"8.4\"\n\n[source]\npaths = [\"fixture.php\"]\n\n[extension-hosts.simbiat]\ncommand = ".\json_encode($command, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n",
        );

        return $directory;
    }

    /**
     * @param string       $directory
     * @param list<string> $arguments
     *
     * @return array{stdout: string, stderr: string, code: int}
     */
    private static function mago(string $directory, array $arguments): array
    {
        $process = \proc_open(
            [self::binary(), ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $directory,
        );
        if (!\is_resource($process)) {
            throw new \RuntimeException('Cannot start '.self::binary().'. Install it ("composer install" gives vendor/bin/mago), or set MAGO_BIN.');
        }

        $stdout = (string)\stream_get_contents($pipes[1]);
        $stderr = (string)\stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $code = \proc_close($process);

        // The workers have ended with Mago: their coverage data is ready.
        WorkerCoverage::collect();

        return ['stdout' => $stdout, 'stderr' => $stderr, 'code' => $code];
    }

    /**
     * @return list<array{code: string, line: int, start: int, end: int, fixable: bool, message: string, level: string}>
     */
    private static function parseIssues(string $json, string $stderr): array
    {
        // Mago prints nothing but the report to the standard output. If it is not JSON, Mago failed (a broken extension, for example).
        try {
            $report = \json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('Mago did not give a JSON report. Output: '.$json.' Errors: '.$stderr);
        }

        $issues = [];
        foreach ($report['issues'] ?? [] as $issue) {
            $line = null;
            $start = 0;
            $end = 0;
            foreach ($issue['annotations'] ?? [] as $annotation) {
                if (($annotation['kind'] ?? '') === 'Primary') {
                    // Mago counts lines from 0.
                    $line = $annotation['span']['start']['line'];
                    // The byte offsets of the range, which the result points at.
                    $start = $annotation['span']['start']['offset'];
                    $end = $annotation['span']['end']['offset'];
                    break;
                }
            }

            $issues[] = [
                'code' => $issue['code'],
                'line' => $line ?? 0,
                'start' => $start,
                'end' => $end,
                'fixable' => ($issue['edits'] ?? []) !== [],
                'message' => $issue['message'],
                'level' => $issue['level'],
            ];
        }

        \usort($issues, static fn(array $a, array $b): int => [$a['line'], $a['code']] <=> [$b['line'], $b['code']]);

        return $issues;
    }

    private static function remove(string $directory): void
    {
        foreach (\glob($directory.'/{,.}*', \GLOB_BRACE) ?: [] as $path) {
            if (\is_file($path)) {
                \unlink($path);
            }
        }

        \rmdir($directory);
    }
}
