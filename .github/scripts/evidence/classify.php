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
// touches, and whether a database schema change comes with its migration.
//
// Tiers:
//   T0  only documentation, tests, translations or CI files change;
//   T2  a security, persistence or API surface path changes, or more than
//       500 lines change;
//   T1  everything else.
//
// The globs below are matched with fnmatch() without FNM_PATHNAME: `*` also
// matches `/`. .github/labeler.yml applies the same tiers as labels: keep both
// lists in step.
//
// Usage: php classify.php <base-ref> <head-ref> <output.json>

if (4 !== $argc) {
    fwrite(\STDERR, "Usage: php classify.php <base-ref> <head-ref> <output.json>\n");
    exit(2);
}

[, $base, $head, $output] = $argv;

const LARGE_CHANGE_LINES = 500;

const T0_PATHS = [
    '*.md',
    'docs/*',
    'tests/*',
    '.github/*',
    'core/lib/Thelia/Config/I18n/*',
    'core/lib/Thelia/Install/I18n/*',
    'setup/I18n/*',
    'local/I18n/*',
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

$risks = [];
foreach (RISK_PATHS as $risk => $globs) {
    $matched = array_values(array_filter($paths, static fn (string $path): bool => matchesAny($path, $globs)));
    if ([] !== $matched) {
        $risks[$risk] = $matched;
    }
}

$reasons = [];
if ([] !== $paths && [] === array_filter($paths, static fn (string $path): bool => !matchesAny($path, T0_PATHS))) {
    $tier = 'T0';
    $reasons[] = 'only documentation, tests, translations or CI files change';
} elseif ([] !== $risks || $changedLines > LARGE_CHANGE_LINES) {
    $tier = 'T2';
    foreach (array_keys($risks) as $risk) {
        $reasons[] = "touches the $risk paths";
    }
    if ($changedLines > LARGE_CHANGE_LINES) {
        $reasons[] = "$changedLines changed lines (more than ".LARGE_CHANGE_LINES.')';
    }
} else {
    $tier = 'T1';
    $reasons[] = 'no risk path, '.$changedLines.' changed lines';
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
];

file_put_contents($output, json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)."\n");
echo "Tier $tier: ".implode('; ', $reasons)."\n";
