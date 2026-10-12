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

// Writes the result of a mutation testing run to the job summary of a GitHub
// workflow, and marks with a notice each changed line that no unit test executes.
//
// Infection annotates the escaped mutants itself. It says nothing about the lines
// the unit suite never runs: no mutant of theirs can be killed, so they cannot be
// checked at all. Without the notice, such a change would look as quiet as a well
// tested one.
//
// Usage: php .github/scripts/mutation-summary.php <infection.json> <summary.md> [<scope>]
//
// <scope> names what a run other than the pull request check mutated and with
// which suites (the nightly run: "Domain/Order, unit and integration suites").

if (3 !== $argc && 4 !== $argc) {
    fwrite(\STDERR, "Usage: php mutation-summary.php <infection.json> <summary.md> [<scope>]\n");
    exit(2);
}

[, $reportPath, $summaryPath] = $argv;
$scope = $argv[3] ?? null;
$heading = null === $scope ? "### Mutation testing\n\n" : "### Mutation testing: {$scope}\n\n";
$tests = null === $scope ? 'unit tests' : 'tests';
$test = null === $scope ? 'unit test' : 'test';

if (!is_file($reportPath)) {
    file_put_contents(
        $summaryPath,
        $heading."Infection wrote no report: the log of the previous step tells why.\n",
        \FILE_APPEND,
    );
    exit(0);
}

$report = json_decode((string) file_get_contents($reportPath), true, flags: \JSON_THROW_ON_ERROR);
$projectDirectory = rtrim((string) getcwd(), '/').'/';

$relativePath = static fn (array $mutant): string => str_starts_with($mutant['mutator']['originalFilePath'], $projectDirectory)
    ? substr($mutant['mutator']['originalFilePath'], strlen($projectDirectory))
    : $mutant['mutator']['originalFilePath'];

$stats = $report['stats'];

$summary = $heading;

if (0 === $stats['totalMutantsCount']) {
    $summary .= "The changed lines hold nothing to mutate.\n";
    file_put_contents($summaryPath, $summary, \FILE_APPEND);
    exit(0);
}

// Label, count, shown even when the count is zero.
$rows = [
    ["Killed by a {$test}", $stats['killedCount'], true],
    ["Escaped: no {$test} noticed the change", $stats['escapedCount'], true],
    ["Not checked: no {$test} executes the line", $stats['notCoveredCount'], true],
    ['Timed out (counted as killed)', $stats['timeOutCount'], false],
    ['Made PHP fail (counted as killed)', $stats['errorCount'] + $stats['syntaxErrorCount'], false],
];

$summary .= "| Mutants | Count |\n|---|---:|\n";
foreach ($rows as [$label, $count, $shownWhenZero]) {
    if ($shownWhenZero || 0 < $count) {
        $summary .= "| {$label} | {$count} |\n";
    }
}

$summary .= sprintf(
    "\nMutation score: %s %% of all the mutants, %s %% of those on lines the {$tests} execute.\n",
    round((float) $stats['msi'], 1),
    round((float) $stats['coveredCodeMsi'], 1),
);

if ([] !== $report['escaped']) {
    $summary .= "\n#### Escaped mutants\n\nA test should fail on each of these changes.\n";
    foreach ($report['escaped'] as $mutant) {
        $summary .= sprintf(
            "\n<details><summary><code>%s:%d</code> %s</summary>\n\n```diff\n%s\n```\n\n</details>\n",
            $relativePath($mutant),
            $mutant['mutator']['originalStartLine'],
            $mutant['mutator']['mutatorName'],
            trim($mutant['diff']),
        );
    }
}

$uncoveredLines = [];
foreach ($report['uncovered'] as $mutant) {
    $uncoveredLines[$relativePath($mutant)][$mutant['mutator']['originalStartLine']] = true;
}

if ([] !== $uncoveredLines) {
    ksort($uncoveredLines);
    $summary .= null === $scope
        ? "\n#### Lines no unit test executes\n\nThe integration, API and HTTP suites may run them, but mutation testing does not run those suites.\n\n"
        : "\n#### Lines no test of this run executes\n\nThe API and HTTP suites may run them; this run does not.\n\n";

    foreach ($uncoveredLines as $file => $lines) {
        $lines = array_keys($lines);
        sort($lines);

        $ranges = [];
        foreach ($lines as $line) {
            $last = array_key_last($ranges);
            if (null !== $last && $ranges[$last][1] + 1 === $line) {
                $ranges[$last][1] = $line;
            } else {
                $ranges[] = [$line, $line];
            }
        }

        $labels = [];
        foreach ($ranges as [$start, $end]) {
            $labels[] = $start === $end ? (string) $start : "{$start}-{$end}";
            echo sprintf(
                "::notice file=%s,line=%d,endLine=%d,title=Not checked by mutation testing::No {$test} executes this code, so mutation testing cannot tell whether a test would notice a change to it.\n",
                strtr($file, ['%' => '%25', ',' => '%2C', ':' => '%3A']),
                $start,
                $end,
            );
        }

        $summary .= sprintf("- `%s`: line%s %s\n", $file, 1 < count($lines) ? 's' : '', implode(', ', $labels));
    }
}

file_put_contents($summaryPath, $summary, \FILE_APPEND);
