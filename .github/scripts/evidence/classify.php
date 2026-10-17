<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Classifies the changes of a pull request: the review tier, the risk areas it
// touches, whether a database schema change comes with its migration, and
// whether the tests lose something.
//
// Tiers:
//   T2  a security, persistence, API surface, installer or CI path changes, or
//       more than 500 lines change outside the T0 paths;
//   T0  only documentation, tests or translation catalogs change, and no test
//       is removed, emptied or skipped;
//   T1  everything else.
//
// T2 wins over T0: a test named after an upload is T2. Nothing that runs in
// production or in a privileged workflow is T0: the PHP translation arrays, the
// update instructions read by the updater, the test bootstrap and the shared
// test support code are T1 at least.
//
// The globs below are matched with fnmatch() without FNM_PATHNAME: `*` also
// matches `/`. .github/labeler.yml applies the same path tiers as labels;
// .github/scripts/evidence/check-tier-parity.mjs checks that both agree.
//
// Usage: php classify.php <base-ref> <head-ref> <output.json>
//        php classify.php --paths <file>
//        (the path tier of each line of <file>: the paths of one change, separated by tabs)

const LARGE_CHANGE_LINES = 500;

const T0_PATHS = [
    '*.md',
    '*.xlf',
    'translations/*.yml',
    'translations/*.yaml',
    '*/translations/*.yml',
    '*/translations/*.yaml',
    'tests/Api/*',
    'tests/Http/*',
    'tests/Integration/*',
    'tests/Unit/*',
    'tests/fixtures/*',
    'tests/Playwright/specs/*',
];

// Never T0, even when a T0 glob matches.
const T0_EXCLUDED_PATHS = [
    'setup/update/instruction/*',
];

const RISK_PATHS = [
    'security' => [
        'core/lib/Thelia/Api/*',
        'core/lib/Thelia/Core/Security/*',
        'core/lib/Thelia/Form/*',
        'core/lib/Thelia/Controller/*',
        '*Upload*',
        '*Mailer*',
        '*Voter*',
    ],
    'persistence' => [
        'core/lib/Thelia/Model/*',
        'core/lib/Thelia/Api/Bridge/*',
        'core/lib/Thelia/Core/Template/Loop/*',
        'local/config/schema.xml',
        'core/lib/Thelia/Config/*.xml',
    ],
    'api_surface' => [
        'core/lib/Thelia/Api/Resource/*',
    ],
    'installer' => [
        'core/lib/Thelia/Install/*',
        'core/lib/Thelia/Core/Install/*',
    ],
    'ci' => [
        '.github/workflows/*',
        '.github/scripts/*',
        '.github/labeler.yml',
    ],
];

function git(string ...$arguments): string
{
    $command = 'git '.implode(' ', array_map('escapeshellarg', $arguments));
    exec($command, $lines, $status);
    if (0 !== $status) {
        fwrite(\STDERR, "Command failed: $command\n");
        exit(1);
    }

    return implode("\n", $lines);
}

function matchesAny(string $path, array $globs): bool
{
    foreach ($globs as $glob) {
        if (fnmatch($glob, $path)) {
            return true;
        }
    }

    return false;
}

function isT0Path(string $path): bool
{
    return matchesAny($path, T0_PATHS) && !matchesAny($path, T0_EXCLUDED_PATHS);
}

/** @return array<string, list<string>> */
function risksOf(array $paths): array
{
    $risks = [];
    foreach (RISK_PATHS as $risk => $globs) {
        $matched = array_values(array_filter($paths, static fn (string $path): bool => matchesAny($path, $globs)));
        if ([] !== $matched) {
            $risks[$risk] = $matched;
        }
    }

    return $risks;
}

// The tier from the paths alone, as .github/labeler.yml sees it.
function pathTier(array $paths): string
{
    if ([] !== risksOf($paths)) {
        return 'T2';
    }

    return [] !== $paths && [] === array_filter($paths, static fn (string $path): bool => !isT0Path($path)) ? 'T0' : 'T1';
}

/** @return list<string> */
function testMethods(string $code): array
{
    preg_match_all('/function\s+(test\w*)\s*\(/', $code, $named);
    preg_match_all('/#\[\s*(?:\\\\?PHPUnit\\\\Framework\\\\Attributes\\\\)?Test\s*\][^{;]*?function\s+(\w+)\s*\(/s', $code, $attributed);

    return array_values(array_unique([...$named[1], ...$attributed[1]]));
}

function countMatches(string $pattern, string $code): int
{
    return (int) preg_match_all($pattern, $code);
}

// What the change takes away from the tests: deleted test files, removed test
// methods, new skips, fewer assertion calls. A test moved to another file counts
// as removed from the first one: the reviewer checks where it went.
function testWeakening(string $base, string $head): array
{
    $skip = '/\bmarkTest(?:Skipped|Incomplete)\s*\(/';
    $assertion = '/\b(?:assert|expect)[A-Z]\w*\s*\(/';

    $deleted = [];
    $removedMethods = [];
    $addedSkips = [];
    $assertionsBefore = 0;
    $assertionsAfter = 0;

    $status = git('diff', '--name-status', '-M', $base, $head, '--', 'tests/');
    foreach (array_filter(explode("\n", $status)) as $line) {
        $fields = explode("\t", $line);
        $kind = $fields[0][0];
        $before = 'A' === $kind ? null : $fields[1];
        $after = 'D' === $kind ? null : $fields[\count($fields) - 1];
        if (!str_ends_with((string) ($after ?? $before), '.php')) {
            continue;
        }

        $baseCode = null === $before ? '' : git('show', "$base:$before");
        $headCode = null === $after ? '' : git('show', "$head:$after");

        if ('D' === $kind && str_ends_with($before, 'Test.php')) {
            $deleted[] = $before;
        }
        foreach (array_diff(testMethods($baseCode), testMethods($headCode)) as $method) {
            if ('D' !== $kind) {
                $removedMethods[] = "$after::$method";
            }
        }
        $newSkips = countMatches($skip, $headCode) - countMatches($skip, $baseCode);
        if ($newSkips > 0) {
            $addedSkips[] = "$after (+$newSkips)";
        }
        $assertionsBefore += countMatches($assertion, $baseCode);
        $assertionsAfter += countMatches($assertion, $headCode);
    }

    $reasons = [];
    if ([] !== $deleted) {
        $reasons[] = \count($deleted).' test file(s) deleted';
    }
    if ([] !== $removedMethods) {
        $reasons[] = \count($removedMethods).' test method(s) removed';
    }
    if ([] !== $addedSkips) {
        $reasons[] = 'markTestSkipped or markTestIncomplete added';
    }
    if ($assertionsAfter < $assertionsBefore) {
        $reasons[] = "assertion calls in the changed tests go from $assertionsBefore to $assertionsAfter";
    }

    return [
        'weakened' => [] !== $reasons,
        'reasons' => $reasons,
        'deleted_test_files' => $deleted,
        'removed_test_methods' => $removedMethods,
        'added_skips' => $addedSkips,
        'assertion_calls' => ['before' => $assertionsBefore, 'after' => $assertionsAfter],
    ];
}

if (3 === $argc && '--paths' === $argv[1]) {
    // One change per line, its paths separated by tabs.
    foreach (file($argv[2], \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) as $line) {
        echo $line, "\t", pathTier(explode("\t", $line)), "\n";
    }
    exit(0);
}

if (4 !== $argc) {
    fwrite(\STDERR, "Usage: php classify.php <base-ref> <head-ref> <output.json>\n");
    exit(2);
}

[, $base, $head, $output] = $argv;

$files = [];
foreach (array_filter(explode("\n", git('diff', '--numstat', '-M', $base, $head))) as $line) {
    [$added, $deleted, $path] = explode("\t", $line, 3);
    // A rename reads "old => new" or "dir/{old => new}/file": keep the new path.
    $path = preg_replace('/\{[^{}]* => ([^{}]*)\}/', '$1', $path);
    $path = str_replace('//', '/', preg_replace('/^.* => /', '', $path));
    $files[] = [
        'path' => $path,
        // Binary files report "-".
        'lines' => (int) $added + (int) $deleted,
    ];
}

$paths = array_column($files, 'path');
$changedLines = array_sum(array_column($files, 'lines'));

$risks = risksOf($paths);
$weakening = testWeakening($base, $head);

$reasons = [];
if ([] !== $risks) {
    $tier = 'T2';
    foreach (array_keys($risks) as $risk) {
        $reasons[] = "touches the $risk paths";
    }
} elseif ('T0' === pathTier($paths) && !$weakening['weakened']) {
    $tier = 'T0';
    $reasons[] = 'only documentation, tests or translation catalogs change';
} elseif ($changedLines > LARGE_CHANGE_LINES) {
    $tier = 'T2';
    $reasons[] = "$changedLines changed lines (more than ".LARGE_CHANGE_LINES.')';
} else {
    $tier = 'T1';
    $reasons[] = 'no risk path, '.$changedLines.' changed lines';
}
if ($weakening['weakened']) {
    $reasons[] = 'the tests lose something: '.implode(', ', $weakening['reasons']);
}

// A schema change needs the upgrade script of existing shops and the DDL of a
// fresh install. setup/thelia.sql is the fresh install; setup/update/sql/ holds
// one file per release for the upgrades.
$schema = ['changed' => in_array('local/config/schema.xml', $paths, true)];
if ($schema['changed']) {
    $schema['migration'] = array_values(array_filter($paths, static fn (string $path): bool => fnmatch('setup/update/sql/*.sql', $path)));
    $schema['fresh_install'] = in_array('setup/thelia.sql', $paths, true);
    $schema['ok'] = [] !== $schema['migration'] && $schema['fresh_install'];
}

$result = [
    'tier' => $tier,
    'tier_reasons' => $reasons,
    'changed_files' => count($files),
    'changed_lines' => $changedLines,
    'risks' => (object) $risks,
    'schema' => $schema,
    'test_weakening' => $weakening,
];

file_put_contents($output, json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)."\n");
echo "Tier $tier: ".implode('; ', $reasons)."\n";
