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

// Gathers the results of the evidence jobs into one evidence.json and writes
// the Markdown report shown in the job summary and in the pull request comment.
//
// Each job leaves its result in <parts-directory>; a missing file means the job
// did not run to the end, and the report says so rather than reading it as a
// clean result.
//
// Usage: php summary.php <parts-directory> <evidence.json> <report.md> <run-url>

if (5 !== $argc) {
    fwrite(\STDERR, "Usage: php summary.php <parts-directory> <evidence.json> <report.md> <run-url>\n");
    exit(2);
}

[, $parts, $evidenceFile, $reportFile, $runUrl] = $argv;

$repositoryUrl = preg_replace('#/actions/runs/.*$#', '', $runUrl);

const MARKER = '<!-- pr-evidence -->';
const LIST_LIMIT = 30;

function part(string $directory, string $name): ?array
{
    $file = "$directory/$name.json";
    if (!is_file($file)) {
        return null;
    }

    $data = json_decode((string) file_get_contents($file), true);

    return is_array($data) ? $data : null;
}

function bullets(array $items): string
{
    $lines = array_map(static fn (string $item): string => '- `'.str_replace('`', "'", $item).'`', array_slice($items, 0, LIST_LIMIT));
    if (count($items) > LIST_LIMIT) {
        $lines[] = '- ... and '.(count($items) - LIST_LIMIT).' more in evidence.json';
    }

    return implode("\n", $lines);
}

function details(string $title, string $body): string
{
    return "<details><summary>$title</summary>\n\n$body\n\n</details>\n";
}

$evidence = [
    'classification' => part($parts, 'classify'),
    'prove_red' => part($parts, 'prove-red'),
    'surface' => part($parts, 'surface'),
    'openapi' => part($parts, 'openapi'),
    'php_api' => part($parts, 'roave'),
    'duplication' => part($parts, 'jscpd'),
    'timing' => part($parts, 'timing'),
];

$rows = [];
$sections = [];
$missing = 'not available: the job did not finish, see the run';

// Classification and schema.
$classification = $evidence['classification'];
$tier = $classification['tier'] ?? '?';
$schema = $classification['schema'] ?? null;
if (null === $schema) {
    $rows[] = ['Database schema', $missing];
} elseif (!$schema['changed']) {
    $rows[] = ['Database schema', 'not changed'];
} elseif ($schema['ok']) {
    $rows[] = ['Database schema', 'changed, with its migration ('.implode(', ', $schema['migration']).') and setup/thelia.sql'];
} else {
    $lacking = [];
    if ([] === $schema['migration']) {
        $lacking[] = 'no setup/update/sql/*.sql change';
    }
    if (!$schema['fresh_install']) {
        $lacking[] = 'no setup/thelia.sql change';
    }
    $rows[] = ['Database schema', '**attention**: schema.xml changed, '.implode(', ', $lacking)];
}

// Tests seen red.
$proveRed = $evidence['prove_red'];
if (null === $proveRed) {
    $rows[] = ['Tests seen red on the base code', $missing];
} elseif ([] === $proveRed['tests']) {
    $rows[] = ['Tests seen red on the base code', 'no test added or changed'];
} else {
    $byStatus = [];
    foreach ($proveRed['tests'] as $test) {
        $byStatus[$test['status']][] = $test['test'];
    }
    $counts = [];
    foreach ($byStatus as $status => $tests) {
        $counts[] = count($tests).' '.$status;
    }
    $prefix = isset($byStatus['green']) ? '**attention**: ' : '';
    $rows[] = ['Tests seen red on the base code', $prefix.implode(', ', $counts)];
    $body = "Each test file the pull request adds or changes, run against the base code with the tests of the pull request.\n"
        ."`green` means the test passes before the change too: it would not catch a regression of it.\n";
    foreach ($byStatus as $status => $tests) {
        $body .= "\n**$status**\n\n".bullets($tests)."\n";
    }
    $sections[] = details('Tests seen red', $body);
}

// Twig, routes, event listeners.
$surface = $evidence['surface'];
if (null === $surface) {
    foreach (['Twig callables and globals', 'Routes', 'Event listeners'] as $label) {
        $rows[] = [$label, $missing];
    }
} else {
    $flagged = static fn (array $diff): bool => [] !== $diff['removed'] || [] !== $diff['changed'];
    $count = static fn (array $diff): string => count($diff['removed']).' removed, '.count($diff['changed']).' changed, '.count($diff['added']).' added';
    $describe = static fn (array $diff): string => ($flagged($diff) ? '**attention**: ' : '').$count($diff);

    if (isset($surface['twig'])) {
        $kinds = [];
        $twigFlagged = false;
        $body = '';
        foreach ($surface['twig'] as $kind => $diff) {
            $kinds[] = "$kind ".$count($diff);
            $twigFlagged = $twigFlagged || $flagged($diff);
            if ([] !== $diff['removed']) {
                $body .= "\n**$kind removed**\n\n".bullets($diff['removed'])."\n";
            }
            if ([] !== $diff['changed']) {
                $body .= "\n**$kind with another signature**\n\n".bullets(array_column($diff['changed'], 'name'))."\n";
            }
        }
        $rows[] = ['Twig callables and globals', ($twigFlagged ? '**attention**: ' : '').implode('; ', $kinds)];
        if ('' !== $body) {
            $sections[] = details('Twig callables and globals', $body);
        }
    } else {
        $rows[] = ['Twig callables and globals', 'not compared, see the run'];
    }

    if (isset($surface['routes'])) {
        $routes = $surface['routes'];
        $rows[] = ['Routes', $describe($routes)];
        $body = '';
        if ([] !== $routes['removed']) {
            $body .= "**removed**\n\n".bullets($routes['removed'])."\n";
        }
        if ([] !== $routes['changed']) {
            $body .= "\n**changed path, method, host or scheme**\n\n".bullets(array_map(
                static fn (array $route): string => $route['name'].': '.$route['base']['method'].' '.$route['base']['path'].' -> '.$route['pr']['method'].' '.$route['pr']['path'],
                $routes['changed'],
            ))."\n";
        }
        if ('' !== $body) {
            $sections[] = details('Routes', $body);
        }
    } else {
        $rows[] = ['Routes', 'not compared, see the run'];
    }

    if (isset($surface['events'])) {
        $listeners = $surface['events']['listeners'];
        $rows[] = ['Event listeners', $describe($listeners).' (a change is a new priority)'];
        $body = '';
        if ([] !== $listeners['removed']) {
            $body .= "**removed**\n\n".bullets($listeners['removed'])."\n";
        }
        if ([] !== $listeners['changed']) {
            $body .= "\n**new priority**\n\n".bullets(array_column($listeners['changed'], 'name'))."\n";
        }
        if ('' !== $body) {
            $sections[] = details('Event listeners', $body);
        }
    } else {
        $rows[] = ['Event listeners', 'not compared, see the run'];
    }

    foreach ($surface['errors'] ?? [] as $error) {
        $rows[] = ['Surface snapshot', "**not compared**: $error"];
    }
}

// API.
$openapi = $evidence['openapi'];
if (null === $openapi) {
    $rows[] = ['API (OpenAPI)', $missing];
} elseif (!($openapi['ran'] ?? false)) {
    $rows[] = ['API (OpenAPI)', '**not compared**: '.($openapi['error'] ?? 'see the run')];
} else {
    $changes = $openapi['changes'];
    $breaking = array_filter($changes, static fn (array $change): bool => ($change['level'] ?? 0) >= 2);
    // A field removed from a response is "info" for oasdiff: API Platform marks
    // no response field as required. It still breaks a client that reads it.
    $removals = array_filter($changes, static fn (array $change): bool => str_contains($change['id'] ?? '', 'removed'));
    $flag = [] !== $breaking || [] !== $removals ? '**attention**: ' : '';
    $rows[] = ['API (OpenAPI)', $flag.count($breaking).' breaking, '.count($removals).' removal(s), '.count($changes).' change(s) in total'];
    $format = static function (array $change): string {
        $where = trim(($change['operation'] ?? '').' '.($change['path'] ?? ''));

        return ('' !== $where ? "$where: " : '').$change['text'].' ['.$change['id'].']';
    };
    $body = '';
    if ([] !== $breaking) {
        $body .= "**breaking (oasdiff level warning or error)**\n\n".bullets(array_values(array_map($format, $breaking)))."\n";
    }
    if ([] !== $removals) {
        $body .= "\n**removals**\n\n".bullets(array_values(array_map($format, $removals)))."\n";
    }
    if ('' !== $body) {
        $sections[] = details('API (OpenAPI)', $body);
    }
}

// PHP API of core.
$phpApi = $evidence['php_api'];
if (null === $phpApi) {
    $rows[] = ['PHP API of core', $missing];
} elseif (!$phpApi['ran']) {
    $rows[] = ['PHP API of core', '**not compared**: the BC check failed, see the run'];
} else {
    $breaks = $phpApi['breaks'];
    $rows[] = ['PHP API of core', ([] !== $breaks ? '**attention**: ' : '').count($breaks).' break(s) reported (constructors of services are outside the promise)'];
    if ([] !== $breaks) {
        $sections[] = details('PHP API of core', bullets($breaks)."\n");
    }
}

// Duplication.
$duplication = $evidence['duplication'];
if (null === $duplication) {
    $rows[] = ['New duplicated code', $missing];
} else {
    $clones = $duplication['new_clones_in_changed_files'];
    $rows[] = ['New duplicated code', ([] !== $clones ? '**attention**: ' : '').count($clones).' new clone(s) involving a changed file'];
    if ([] !== $clones) {
        $sections[] = details('New duplicated code', bullets(array_map(
            static fn (array $clone): string => $clone['lines'].' lines: '.$clone['first'].' and '.$clone['second'],
            $clones,
        ))."\n");
    }
}

// Timing.
$timing = $evidence['timing'];
$duration = isset($timing['total_seconds']) ? sprintf('%d min %02d s', intdiv($timing['total_seconds'], 60), $timing['total_seconds'] % 60) : 'unknown';

$report = MARKER."\n## Evidence report\n\n"
    ."Tier **$tier**: ".implode('; ', $classification['tier_reasons'] ?? ['not classified'])
    .'. '.($classification['changed_files'] ?? '?').' file(s), '.($classification['changed_lines'] ?? '?')." changed line(s).\n\n"
    ."These checks flag changes for the review; none of them blocks the merge. What counts as a break: [BC-PROMISE.md]($repositoryUrl/blob/main/BC-PROMISE.md).\n\n"
    ."| Check | Result |\n|---|---|\n"
    .implode("\n", array_map(static fn (array $row): string => '| '.$row[0].' | '.str_replace('|', '\|', $row[1]).' |', $rows))
    ."\n\n".implode("\n", $sections)
    ."\nFull data: the `evidence` artifact of [the run]($runUrl) (evidence.json). Duration: $duration.\n";

// A pull request comment holds at most 65536 characters.
if (strlen($report) > 60000) {
    $report = substr($report, 0, 60000)."\n\n... (truncated, see evidence.json)\n";
}

$evidence['run_url'] = $runUrl;
file_put_contents($evidenceFile, json_encode($evidence, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)."\n");
file_put_contents($reportFile, $report);
