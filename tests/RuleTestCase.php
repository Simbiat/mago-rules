<?php

declare(strict_types=1);

namespace Simbiat\MagoRules\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simbiat\MagoRules\Tests\Support\MagoRunner;

/**
 * Base class for the tests of the rules of one fixture folder.
 *
 * A test class declares the codes of its rules. The fixtures are `.inc` files in
 * `tests/Fixtures/<Category>/<Name>/`, where `<Category>` and `<Name>` are the namespace and the name of the test class (without `Test`).
 * This class runs the real `mago` on each fixture.
 *
 * Fixture directives (all in `//` comments):
 *
 *  - `// @expect simbiat/code-one simbiat/code-two`  The line must give these results (the codes of the rules).
 *  - `// @expect-next simbiat/code-one`              The NEXT line must give these results. Use this for
 *                                                    multi-line strings and heredocs, where a comment cannot
 *                                                    go on the line itself.
 *  - `// @expect+2 simbiat/code-one`                 The same, but N lines below (here: 2). `@expect-next` is `@expect+1`.
 *  - `// @property precision=N`                      Set the `--sql-precision` option of the worker for the whole fixture.
 *  - `// @max-passes N`                              The fixer may need up to N passes for this fixture (default: 1,
 *                                                    one run of `mago lint --fix`). A higher value is for fixtures that really
 *                                                    need several passes.
 *  - `// @known-issue Text`                          The fixture describes DESIRED behavior that the rule does not
 *                                                    have yet (or a wrong fixer output). `@expect` and `.fixed` hold the
 *                                                    CORRECT result. While the rule is wrong, the tests are reported as
 *                                                    "incomplete", not as "failed". When the rule becomes correct, the
 *                                                    tests fail, and tell you to remove the directive.
 *
 * A line without `@expect` must give NO result. This is how fixtures prove "must not be flagged".
 *
 * Fixer: if `Fixture.inc.fixed` exists, the fixer output must be equal to it. If it does not exist,
 * the fixer must not change the fixture.
 *
 * Only the rules of the test class run on a fixture (`mago lint --only`), so the native rules of Mago do not get in the way.
 */
abstract class RuleTestCase extends TestCase
{
    /**
     * Results of the runs, so a fixture is not run again for each test method. The runs are slow: Mago starts a PHP process for each of them.
     *
     * @var array<string, mixed>
     */
    private static array $cache = [];

    /**
     * Every code of the rules that the fixtures of this test class check, mapped to "can the fixer fix it?".
     *
     * @return array<string, bool>
     */
    abstract public static function ruleCodes(): array;

    /**
     * The codes, that can give a result without a fix, even though they are declared as fixable.
     * It is for a rule, that leaves a part of its results to another rule of the same fixture folder.
     *
     * @return list<string>
     */
    protected static function mayBeUnfixable(): array
    {
        return [];
    }

    /**
     * @return array<string, array{string}> Fixture name => [absolute path]
     */
    public static function fixtures(): array
    {
        $files = \glob(self::fixtureDirectory().'/*.inc');
        $result = [];
        foreach ($files === false ? [] : $files as $path) {
            $result[\basename($path)] = [$path];
        }

        return $result;
    }

    #[DataProvider('fixtures')]
    public function testRulesGiveExactlyTheExpectedResults(string $fixture): void
    {
        $expected = self::expectedResults($fixture);
        $actual = self::actualResults($this->lint($fixture));

        $known_issue = self::knownIssue($fixture);
        if ($known_issue !== null) {
            if ($expected !== $actual) {
                self::markTestIncomplete('Known issue: '.$known_issue);
            }

            // The report is right. The issue is fixed only if the fixer is right too.
            [$expected_fixed, $actual_fixed] = $this->fixerResult($fixture);
            self::assertNotSame(
                $expected_fixed,
                $actual_fixed,
                'The known issue in '.\basename($fixture).' seems to be fixed. Remove the @known-issue directive.',
            );
            self::markTestIncomplete('Known issue: '.$known_issue);
        }

        self::assertSame(
            $expected,
            $actual,
            'Results of '.\basename($fixture).' are different from the @expect directives (line => codes).',
        );
    }

    #[DataProvider('fixtures')]
    public function testFixableFlagMatchesTheDeclaredCodes(string $fixture): void
    {
        $codes = static::ruleCodes();

        foreach ($this->lint($fixture) as $issue) {
            self::assertArrayHasKey($issue['code'], $codes, 'Line '.$issue['line'].' gave an undeclared code: '.$issue['code']);

            if ($codes[$issue['code']] === $issue['fixable'] || ($codes[$issue['code']] && \in_array($issue['code'], static::mayBeUnfixable(), true))) {
                continue;
            }

            self::fail('Line '.$issue['line'].': "fixable" flag is wrong for '.$issue['code']);
        }
    }

    #[DataProvider('fixtures')]
    public function testMessagesHaveNoUnreplacedPlaceholders(string $fixture): void
    {
        foreach ($this->lint($fixture) as $issue) {
            self::assertDoesNotMatchRegularExpression(
                '~%(?:\d+\$)?[sd]~',
                $issue['message'],
                'Line '.$issue['line'].' of '.\basename($fixture).' has a message with a placeholder that was not replaced.',
            );
        }
    }

    #[CoversNothing]
    #[DataProvider('fixtures')]
    public function testFixtureIsValidPhp(string $fixture): void
    {
        self::assertValidPhp(self::read($fixture), \basename($fixture));
    }

    #[DataProvider('fixtures')]
    public function testFixerOutput(string $fixture): void
    {
        [$expected, $actual] = $this->fixerResult($fixture);

        $known_issue = self::knownIssue($fixture);
        if ($known_issue !== null && $expected !== $actual) {
            self::markTestIncomplete('Known issue: '.$known_issue);
        }

        self::assertSame($expected, $actual, 'Fixer output is different from the expected file.');

        // The fixed code must be stable: the rules must not want to fix it again.
        // This applies only when the fixer changed the file (a fixture can show a problem that the fixer leaves alone on purpose).
        if ($expected !== self::read($fixture)) {
            $again = MagoRunner::lint($expected, \array_keys(static::ruleCodes()), self::precision($fixture));

            self::assertSame(
                [],
                \array_values(\array_filter($again, static fn(array $issue): bool => $issue['fixable'])),
                'The fixed file still has fixable results: the fix is not stable.',
            );
        }
    }

    #[DataProvider('fixtures')]
    public function testFixerOutputIsValidPhp(string $fixture): void
    {
        $known_issue = self::knownIssue($fixture);
        try {
            self::assertValidPhp($this->fix($fixture)['contents'], 'The fixer output of '.\basename($fixture));
        } catch (\Throwable $exception) {
            if ($known_issue !== null) {
                self::markTestIncomplete('Known issue: '.$known_issue);
            }

            throw $exception;
        }
    }

    #[CoversNothing]
    public function testEveryDeclaredCodeIsCoveredByAFixture(): void
    {
        $seen = [];
        foreach (self::fixtures() as [$fixture]) {
            foreach (self::expectedResults($fixture) as $codes) {
                foreach ($codes as $code) {
                    $seen[$code] = true;
                }
            }
        }

        foreach (\array_keys(static::ruleCodes()) as $code) {
            self::assertTrue(isset($seen[$code]), 'No fixture expects the code '.$code.'.');
        }
    }

    /**
     * @return array{0: string, 1: string} The expected file, and what the fixer made.
     */
    private function fixerResult(string $fixture): array
    {
        $had_fixable = \array_any($this->lint($fixture), static fn(array $issue): bool => $issue['fixable']);
        $fixed_path = $fixture.'.fixed';

        if ($had_fixable && !\is_file($fixed_path)) {
            self::fail(\basename($fixture).' has fixable results, but '.\basename($fixed_path).' does not exist.');
        }

        $result = $this->fix($fixture);
        if ($had_fixable && self::knownIssue($fixture) === null) {
            self::assertTrue($result['converged'], 'The fixer did not finish for '.\basename($fixture).' (the fixes keep changing the file).');

            // If a rule makes only a part of its edits in a pass, the next pass makes the rest, and the final output is
            // the same. The number of passes shows it.
            self::assertLessThanOrEqual(
                self::maxPasses($fixture),
                $result['passes'],
                'The fixer needed more passes than expected for '.\basename($fixture).'. If this is right, add `// @max-passes N` to the fixture.',
            );
        }

        return [\is_file($fixed_path) ? self::read($fixed_path) : self::read($fixture), $result['contents']];
    }

    /**
     * @return list<array{code: string, line: int, fixable: bool, message: string, level: string}>
     */
    private function lint(string $fixture): array
    {
        /** @var list<array{code: string, line: int, fixable: bool, message: string, level: string}> */
        return self::$cache['lint:'.static::class.$fixture] ??= MagoRunner::lint(self::read($fixture), \array_keys(static::ruleCodes()), self::precision($fixture));
    }

    /**
     * @return array{contents: string, passes: int, converged: bool}
     */
    private function fix(string $fixture): array
    {
        /** @var array{contents: string, passes: int, converged: bool} */
        return self::$cache['fix:'.static::class.$fixture] ??= MagoRunner::fix(self::read($fixture), \array_keys(static::ruleCodes()), self::precision($fixture));
    }

    /**
     * The folder of the fixtures of the test class, derived from its namespace and name: `...\Tests\SQL\DateTimeFunctionsTest` => `tests/Fixtures/SQL/DateTimeFunctions`.
     */
    protected static function fixtureDirectory(): string
    {
        $parts = \explode('\\', static::class);
        $name = \substr(\array_pop($parts), 0, -\strlen('Test'));
        $category = \array_pop($parts);

        return MagoRunner::root().'/tests/Fixtures/'.$category.'/'.$name;
    }

    /**
     * @param list<array{code: string, line: int, fixable: bool, message: string, level: string}> $issues
     *
     * @return array<int, list<string>> Line => codes, sorted.
     */
    private static function actualResults(array $issues): array
    {
        $result = [];
        foreach ($issues as $issue) {
            $result[$issue['line']][] = $issue['code'];
        }

        foreach ($result as &$codes) {
            \sort($codes);
        }

        unset($codes);
        \ksort($result);

        return $result;
    }

    /**
     * @return array<int, list<string>> Line => codes, sorted.
     */
    private static function expectedResults(string $fixture): array
    {
        $result = [];
        foreach (self::lines($fixture) as $index => $text) {
            if (\preg_match('~//\s*@expect(-next|\+\d+)?((?:\s+[a-z]+(?:-[a-z]+)*/[a-z]+(?:-[a-z]+)*)+)~', $text, $match) !== 1) {
                continue;
            }

            $offset = match (true) {
                $match[1] === '' => 0,
                $match[1] === '-next' => 1,
                default => (int)\substr($match[1], 1),
            };
            $line = $index + 1 + $offset;
            $codes = \preg_split('/\s+/', \trim($match[2]), -1, \PREG_SPLIT_NO_EMPTY);
            \assert($codes !== false);
            $result[$line] = \array_merge($result[$line] ?? [], $codes);
        }

        foreach ($result as &$codes) {
            \sort($codes);
        }

        unset($codes);
        \ksort($result);

        return $result;
    }

    private static function maxPasses(string $fixture): int
    {
        foreach (self::lines($fixture) as $text) {
            if (\preg_match('~^\s*//\s*@max-passes\s+(\d+)\s*$~', $text, $match) === 1) {
                return (int)$match[1];
            }
        }

        return 1;
    }

    private static function knownIssue(string $fixture): ?string
    {
        foreach (self::lines($fixture) as $text) {
            if (\preg_match('~^\s*//\s*@known-issue\s+(.+)$~', $text, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    private static function precision(string $fixture): int
    {
        foreach (self::lines($fixture) as $text) {
            if (\preg_match('~^\s*//\s*@property\s+precision=(-?\d+)\s*$~', $text, $match) === 1) {
                return (int)$match[1];
            }
        }

        return 0;
    }

    private static function assertValidPhp(string $code, string $what): void
    {
        try {
            \token_get_all($code, \TOKEN_PARSE);
        } catch (\ParseError $error) {
            self::fail($what.' is not valid PHP: '.$error->getMessage().' (line '.$error->getLine().')');
        }

        self::assertTrue(true);
    }

    /**
     * @return list<string>
     */
    private static function lines(string $path): array
    {
        $lines = \preg_split('/\R/', self::read($path));

        return $lines === false ? [] : $lines;
    }

    private static function read(string $path): string
    {
        $content = \file_get_contents($path);
        if ($content === false) {
            self::fail('Cannot read '.$path);
        }

        return $content;
    }
}
