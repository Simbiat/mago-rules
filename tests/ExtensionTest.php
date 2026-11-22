<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests;

use Mago\Sdk\Linter\Rule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simbiat\MagoRules\MagoExtension;
use Simbiat\MagoRules\Tests\Support\MagoRunner;

/**
 * Checks that the package is consistent: every rule file is registered, has a test and fixtures and is documented,
 * and Mago itself can load the extension. It is what `RulesetTest` did for `ruleset.xml`.
 */
#[CoversClass(MagoExtension::class)]
final class ExtensionTest extends TestCase
{
    /**
     * @return array<string, array{string}> Class name => [class name]
     */
    public static function ruleClasses(): array
    {
        $result = [];
        $files = \glob(MagoRunner::root().'/Simbiat/MagoRules/*/*Rule.php');
        foreach ($files === false ? [] : $files as $path) {
            $class = 'Simbiat\\MagoRules\\'.\basename(\dirname($path)).'\\'.\substr(\basename($path), 0, -\strlen('.php'));
            $result[$class] = [$class];
        }

        return $result;
    }

    /**
     * @return array<string, array{string}> Code of the rule => [code]
     */
    public static function ruleCodes(): array
    {
        $result = [];
        foreach (SimbiatExtension::create()->linterRules as $rule) {
            $code = $rule->getDefinition()->code;
            $result[$code] = [$code];
        }

        return $result;
    }

    /**
     * @return array<string, array{string}> Folder => [absolute path]
     */
    public static function fixtureDirectories(): array
    {
        $result = [];
        $directories = \glob(MagoRunner::root().'/tests/Fixtures/*/*', \GLOB_ONLYDIR);
        foreach ($directories === false ? [] : $directories as $path) {
            $result[\basename(\dirname($path)).'/'.\basename($path)] = [$path];
        }

        return $result;
    }

    /**
     * @return array<string, array{string}> Relative path => [absolute path]
     */
    public static function fixedFiles(): array
    {
        $result = [];
        $files = \glob(MagoRunner::root().'/tests/Fixtures/*/*/*.inc.fixed');
        foreach ($files === false ? [] : $files as $path) {
            $result[\substr($path, \strlen(MagoRunner::root()) + 1)] = [$path];
        }

        return $result;
    }

    public function testThereAreRules(): void
    {
        self::assertGreaterThan(0, \count(self::ruleClasses()), 'No rule files found: the glob in this test is wrong.');
    }

    #[DataProvider('ruleClasses')]
    public function testRuleClassIsValid(string $class): void
    {
        self::assertTrue(\class_exists($class), $class.' cannot be loaded (wrong namespace or file name?).');
        self::assertContains(Rule::class, \class_implements($class) ?: [], $class.' must implement the Mago Rule interface.');
    }

    #[DataProvider('ruleClasses')]
    public function testRuleIsRegisteredInTheExtension(string $class): void
    {
        $registered = \array_map(static fn(Rule $rule): string => $rule::class, SimbiatExtension::create()->linterRules);

        self::assertContains($class, $registered, $class.' is not registered in Simbiat/SimbiatExtension.php.');
    }

    #[DataProvider('ruleCodes')]
    public function testRuleCodeIsWellFormedAndDeclaredByTheClass(string $code): void
    {
        self::assertMatchesRegularExpression('~^simbiat/[a-z]+(?:-[a-z]+)*$~', $code);

        $classes = \array_filter(
            SimbiatExtension::create()->linterRules,
            static fn(Rule $rule): bool => $rule->getDefinition()->code === $code,
        );
        self::assertCount(1, $classes, 'The code '.$code.' is used by more than one rule.');
        $class = \array_values($classes)[0]::class;
        self::assertSame($code, \constant($class.'::CODE'), $class.'::CODE must be the same as the code in its definition.');
    }

    #[DataProvider('ruleCodes')]
    public function testRuleHasATestClassWithFixtures(string $code): void
    {
        $owners = [];
        foreach (self::testClasses() as $test_class) {
            if (\array_key_exists($code, $test_class::ruleCodes())) {
                $owners[] = $test_class;
            }
        }

        self::assertCount(1, $owners, $code.' must be declared by exactly one test class, that extends RuleTestCase.');

        $fixtures = \glob(MagoRunner::root().'/tests/Fixtures/'.self::folderOf($owners[0]).'/*.inc');
        self::assertNotEmpty($fixtures, $code.' has no fixtures.');
    }

    #[DataProvider('ruleCodes')]
    public function testRuleIsDocumentedInReadme(string $code): void
    {
        $readme = \file_get_contents(MagoRunner::root().'/README.md');
        self::assertNotFalse($readme);

        self::assertStringContainsString($code, $readme, $code.' is not mentioned in README.md.');
    }

    #[DataProvider('fixtureDirectories')]
    public function testFixtureDirectoryBelongsToATestClass(string $directory): void
    {
        $folders = \array_map(self::folderOf(...), self::testClasses());

        self::assertContains(
            \basename(\dirname($directory)).'/'.\basename($directory),
            $folders,
            $directory.' has fixtures, but no test class uses them.',
        );
    }

    #[DataProvider('fixedFiles')]
    public function testFixedFileHasItsFixture(string $fixedFile): void
    {
        self::assertFileExists(\substr($fixedFile, 0, -\strlen('.fixed')), 'Orphan file: the .inc for '.$fixedFile.' does not exist.');
    }

    public function testMagoLoadsTheExtension(): void
    {
        $list = MagoRunner::extensionList();

        foreach (self::ruleCodes() as [$code]) {
            self::assertStringContainsString($code, $list, 'Mago does not list '.$code.'.');
        }
    }

    /**
     * @return list<class-string<RuleTestCase>>
     */
    private static function testClasses(): array
    {
        $result = [];
        $files = \glob(MagoRunner::root().'/tests/*/*Test.php');
        foreach ($files === false ? [] : $files as $path) {
            $class = 'Simbiat\\MagoRules\\Tests\\'.\basename(\dirname($path)).'\\'.\substr(\basename($path), 0, -\strlen('.php'));
            if (\class_exists($class) && \is_subclass_of($class, RuleTestCase::class)) {
                $result[] = $class;
            }
        }

        return $result;
    }

    /**
     * @param class-string<RuleTestCase> $test_class
     */
    private static function folderOf(string $test_class): string
    {
        $parts = \explode('\\', $test_class);
        $name = \substr(\array_pop($parts), 0, -\strlen('Test'));

        return \array_pop($parts).'/'.$name;
    }
}
