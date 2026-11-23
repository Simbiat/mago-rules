<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests\Support;

use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;
use SebastianBergmann\CodeCoverage\Test\TestSize;
use SebastianBergmann\CodeCoverage\Test\TestStatus;

/**
 * Gives PHPUnit the coverage of the code that runs in a process that Mago (or a test) starts.
 *
 * PHPUnit sees only its own process. The rules run in a worker, so their lines look as "never executed". This class
 * makes the worker collect coverage on its own (`worker-coverage.php`), and merges the data into the coverage of PHPUnit
 * when the process ends.
 *
 * It does nothing when PHPUnit does not collect coverage and Infection does not run: then no process gets an additional setting, and nothing is slower.
 *
 * In a mutation testing run, the same prepend file makes the worker run the mutated code (see {@see self::mutation()}).
 *
 * `PHPUnit\Runner\CodeCoverage` is marked as internal by PHPUnit. If a new PHPUnit version changes it, only this class needs a change.
 */
final class WorkerCoverage
{
    /**
     * The test ID that `worker-coverage.php` uses for the data of a worker.
     */
    public const string WORKER_TEST_ID = 'MagoWorker';

    /**
     * The class of Infection that replaces a file with its mutated copy.
     */
    private const string INTERCEPTOR = 'Infection\\StreamWrapper\\IncludeInterceptor';

    /**
     * The folder where the workers write their data. It is made when it is first needed, and removed at the end of the PHPUnit process.
     */
    private static ?string $directory = null;

    /**
     * Does PHPUnit collect code coverage in this run?
     */
    public static function isEnabled(): bool
    {
        return CodeCoverage::instance()->isActive();
    }

    /**
     * The `-d` settings for a `php` command. They make the process collect coverage and, in a mutation testing run, see the mutated file. There are none when both are off.
     *
     * @param string|null $script        A copy of a file of the package that the process runs. Its coverage is tracked too.
     * @param string|null $coverageAlias The real file that the copy stands for: the data of the copy is counted for it.
     *
     * @return list<string> Arguments for `php`, without the script. They are in pairs of `-d` and the setting.
     */
    public static function phpArguments(?string $script = null, ?string $coverageAlias = null): array
    {
        $prepend = \getenv('MAGO_WORKER_PREPEND');
        $mutation = self::mutation();
        $arguments = [];
        if (!self::isEnabled() && $mutation === null) {
            // The user's file still has to be loaded, as it was before.
            if (\is_string($prepend) && $prepend !== '') {
                \array_push($arguments, '-d', 'auto_prepend_file='.$prepend);
            }

            return $arguments;
        }

        \array_push($arguments, '-d', 'auto_prepend_file='.__DIR__.'/worker-coverage.php');
        if (self::isEnabled()) {
            \array_push($arguments, '-d', 'mago_rules.coverage_dir='.self::directory());
        }

        if (\is_string($prepend) && $prepend !== '') {
            \array_push($arguments, '-d', 'mago_rules.prepend='.$prepend);
        }

        if ($mutation !== null) {
            \array_push($arguments, '-d', 'mago_rules.mutation='.$mutation);
        }

        if ($script !== null && $coverageAlias !== null && self::isEnabled()) {
            \array_push($arguments, '-d', 'mago_rules.coverage_alias='.$script.'|'.$coverageAlias);
        }

        return $arguments;
    }

    /**
     * Infection tests a mutant by replacing one file with its mutated copy, but only in the process of PHPUnit: it includes the copy, when the original is requested.
     * The rules run in the worker, which is another process, so without help it would run the original code, and every mutant of a rule would survive.
     * The interceptor of Infection knows which file is replaced by which one. This reads it, so the worker can do the same.
     *
     * @return string|null Hexadecimal JSON of `[interceptor class, interceptor file, original file, mutated file]`. Null if Infection does not run, or if it replaces nothing.
     */
    private static function mutation(): ?string
    {
        foreach (\get_declared_classes() as $class) {
            // Infection can be in a scoped PHAR, then the namespace has a prefix.
            if ($class !== self::INTERCEPTOR && !\str_ends_with($class, '\\'.self::INTERCEPTOR)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            $original = $reflection->getProperty('intercept')->getValue();
            $mutant = $reflection->getProperty('replacement')->getValue();
            if (!\is_string($original) || !\is_string($mutant) || $original === '' || $mutant === '') {
                return null;
            }

            // Hexadecimal: the parser of `php -d` reads `|` and `\` in a value as operators and escapes. Letters and digits are safe.
            return \bin2hex(\json_encode([$class, $reflection->getFileName(), $original, $mutant], \JSON_THROW_ON_ERROR));
        }

        return null;
    }

    /**
     * Merge the data of the processes that have ended into the coverage of PHPUnit, and delete it.
     *
     * The worker does not know which test started it, so its data comes with a fixed test ID ({@see self::WORKER_TEST_ID}).
     * Here it is replaced by the ID of the test that runs now. Tools that read the coverage and the JUnit log together (Infection)
     * need that: they look up every ID of the coverage in the log, and an ID of a test that does not exist stops them.
     */
    public static function collect(): void
    {
        if (self::$directory === null || !self::isEnabled()) {
            return;
        }

        $coverage = CodeCoverage::instance()->codeCoverage();
        $test_id = self::currentTestId();
        foreach (\glob(self::$directory.'/*.cov') ?: [] as $path) {
            $stored = \unserialize((string)\file_get_contents($path), ['allowed_classes' => true]);
            \unlink($path);
            if (!\is_array($stored) || !($stored['data'] ?? null) instanceof ProcessedCodeCoverageData) {
                continue;
            }

            $data = $stored['data'];
            if (\is_string($test_id) && $test_id !== '') {
                $data->setTestIds(\array_map(static fn(string $id): string => $id === self::WORKER_TEST_ID ? $test_id : $id, $data->testIds()));
                // PHPUnit adds the test to the list only if the test itself has coverage of its own. A test that covers nothing has no entry yet.
                $tests = $coverage->getTests();
                $tests[$test_id] ??= ['size' => TestSize::Unknown->asString(), 'status' => TestStatus::Unknown->asString(), 'time' => 0.0];
                $coverage->setTests($tests);
            }

            $coverage->getData(true)->merge($data);
        }
    }

    /**
     * The ID that PHPUnit gives to the test that runs now: `Class::method` and `#data set` for a data provider.
     *
     * PHPUnit does not collect coverage for a test that covers nothing, so the coverage object does not know it. The test is found in the call stack instead.
     *
     * @return non-empty-string|null Null if no test is in the call stack.
     */
    private static function currentTestId(): ?string
    {
        foreach (\debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT | \DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (($frame['object'] ?? null) instanceof TestCase) {
                $id = $frame['object']->valueObjectForEvents()->id();

                return $id === '' ? null : $id;
            }
        }

        return null;
    }

    private static function directory(): string
    {
        if (self::$directory !== null) {
            return self::$directory;
        }

        $directory = \sys_get_temp_dir().'/mago-rules-coverage-'.\bin2hex(\random_bytes(6));
        if (!\mkdir($directory, 0o700) && !\is_dir($directory)) {
            throw new \RuntimeException('Cannot create '.$directory);
        }

        \file_put_contents($directory.'/files.json', \json_encode(CodeCoverage::instance()->codeCoverage()->filter()->files(), \JSON_THROW_ON_ERROR));
        \register_shutdown_function(static function () use ($directory): void {
            foreach (\glob($directory.'/*') ?: [] as $path) {
                \unlink($path);
            }

            \rmdir($directory);
        });

        return self::$directory = $directory;
    }
}
