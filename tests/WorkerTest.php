<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Simbiat\MagoRules\Tests\Support\MagoRunner;
use Simbiat\MagoRules\Tests\Support\WorkerCoverage;

/**
 * Checks `bin/worker.php` as a plain PHP script: the start-up errors that Mago never shows in a normal run.
 *
 * The script runs in a separate process, so PHPUnit cannot attribute its lines to a test. `WorkerCoverage` merges them anyway.
 */
#[CoversNothing]
final class WorkerTest extends TestCase
{
    public function testPrecisionMustBeAnInteger(): void
    {
        $result = self::runScript(MagoRunner::root().'/bin/worker.php', ['--sql-precision=abc']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('--sql-precision must be an integer.', $result['stderr']);
    }

    public function testMissingAutoloaderIsReported(): void
    {
        // The script looks for the autoloader in three places, relative to itself. A copy, that is three levels below `tests/`, finds none.
        // The copy is inside the package on purpose: pcov collects coverage only for files in the folder of the project.
        $root = MagoRunner::root().'/tests/.worker-'.\bin2hex(\random_bytes(6));
        \mkdir($root.'/x/bin', 0o700, true);
        $copy = $root.'/x/bin/worker.php';
        \copy(MagoRunner::root().'/bin/worker.php', $copy);

        try {
            $result = self::runScript($copy, [], MagoRunner::root().'/bin/worker.php');
        } finally {
            \unlink($copy);
            \rmdir($root.'/x/bin');
            \rmdir($root.'/x');
            \rmdir($root);
        }

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('Cannot find the Composer autoloader.', $result['stderr']);
    }

    /**
     * @param list<string> $arguments
     * @param string|null  $coverageAlias The real file that the script stands for. Its coverage is counted for that file.
     *
     * @return array{stderr: string, code: int}
     */
    private static function runScript(string $script, array $arguments, ?string $coverageAlias = null): array
    {
        $process = \proc_open(
            [\PHP_BINARY, ...WorkerCoverage::phpArguments($script, $coverageAlias), $script, ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);

        \fclose($pipes[0]);
        \stream_get_contents($pipes[1]);
        $stderr = (string)\stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $code = \proc_close($process);
        WorkerCoverage::collect();

        return ['stderr' => $stderr, 'code' => $code];
    }
}
