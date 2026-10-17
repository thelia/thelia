#!/usr/bin/env python3
"""Compare the skipped tests of a pull request with those of the base branch.

A test that skips itself keeps a suite green while proving nothing: six
http-backoffice tests went unnoticed in CI for weeks because of it. This script
reads the JUnit reports of two runs and says what changed, suite by suite.

It is a signal, never a gate: every path ends with exit code 0.

Usage: skipped-tests-gate.py <base-dir> <head-dir> [<base-label>]

Each directory holds one sub-directory per test job (`junit-php8.3-api`), which
holds one JUnit file per suite (`api.xml`).
"""

import os
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

MAX_NAMES_IN_ANNOTATION = 8


def read_suite(path):
    """Return (tests, set of skipped test names) of one JUnit file, or None."""
    try:
        root = ET.parse(path).getroot()
    except (ET.ParseError, OSError):
        return None

    tests = 0
    skipped = set()
    for case in root.iter('testcase'):
        tests += 1
        if case.find('skipped') is not None:
            skipped.add('{}::{}'.format(case.get('class') or case.get('classname') or '?', case.get('name') or '?'))

    return tests, skipped


def read_reports(directory):
    """Map (job, suite) to (tests, skipped) for every JUnit file under a directory."""
    reports = {}
    if directory is None or not directory.is_dir():
        return reports

    for job in sorted(p for p in directory.iterdir() if p.is_dir()):
        for report in sorted(job.glob('*.xml')):
            suite = read_suite(report)
            if suite is not None:
                reports[(job.name.removeprefix('junit-'), report.stem)] = suite

    return reports


def escape_annotation(message):
    return message.replace('%', '%25').replace('\r', '%0D').replace('\n', '%0A')


def short(names):
    names = sorted(names)
    shown = [name.replace('Thelia\\Tests\\', '') for name in names[:MAX_NAMES_IN_ANNOTATION]]
    more = len(names) - len(shown)

    return ', '.join(shown) + (' and {} more'.format(more) if more > 0 else '')


def main():
    base_label = sys.argv[3] if len(sys.argv) > 3 else 'main'
    base_dir = Path(sys.argv[1]) if len(sys.argv) > 1 else None
    head_dir = Path(sys.argv[2]) if len(sys.argv) > 2 else None

    base = read_reports(base_dir)
    head = read_reports(head_dir)
    lines = ['## Skipped tests against {}'.format(base_label), '']
    reference = os.environ.get('REFERENCE_RUN_URL')
    if reference:
        lines.extend(['Reference: {}'.format(reference), ''])

    if not head:
        lines.append('No JUnit report was produced by this run: nothing to compare.')
        print('::warning title=Skipped tests::No JUnit report found for this run, the skipped tests were not compared.')
    elif not base:
        lines.append('No JUnit report found on a green run of `{}` yet: nothing to compare against.'.format(base_label))
        lines.append('')
        lines.append('| Job | Suite | Tests | Skipped |')
        lines.append('|---|---|---:|---:|')
        for (job, suite), (tests, skipped) in sorted(head.items()):
            lines.append('| {} | {} | {} | {} |'.format(job, suite, tests, len(skipped)))
    else:
        lines.append('| Job | Suite | Tests ({0} / PR) | Skipped ({0} / PR) | New skips | Skips gone |'.format(base_label))
        lines.append('|---|---|---:|---:|---|---|')
        details = []
        warned = False

        for key in sorted(set(base) | set(head)):
            job, suite = key
            if key not in head:
                lines.append('| {} | {} | {} / - | {} / - | report missing on the PR | |'.format(job, suite, base[key][0], len(base[key][1])))
                print('::warning title=Skipped tests::No JUnit report for {} / {} on this run, the suite may not have run.'.format(job, suite))
                warned = True
                continue
            if key not in base:
                lines.append('| {} | {} | - / {} | - / {} | no reference | |'.format(job, suite, head[key][0], len(head[key][1])))
                continue

            base_tests, base_skipped = base[key]
            head_tests, head_skipped = head[key]
            added = head_skipped - base_skipped
            removed = base_skipped - head_skipped
            lines.append('| {} | {} | {} / {} | {} / {} | {} | {} |'.format(
                job, suite, base_tests, head_tests, len(base_skipped), len(head_skipped), len(added), len(removed)))

            if added:
                warned = True
                details.append('### New skips in {} / {}'.format(job, suite))
                details.extend('- `{}`'.format(name) for name in sorted(added))
                details.append('')
                print('::warning title=Skipped tests ({} / {})::{} test(s) skip on this PR and not on {}: {}'.format(
                    job, suite, len(added), base_label, escape_annotation(short(added))))
            if removed:
                details.append('### Skips gone in {} / {}'.format(job, suite))
                details.extend('- `{}`'.format(name) for name in sorted(removed))
                details.append('')

        lines.append('')
        lines.append('No new skipped test.' if not warned else 'Signal only: this check never fails.')
        if details:
            lines.append('')
            lines.extend(details)

    summary = os.environ.get('GITHUB_STEP_SUMMARY')
    text = '\n'.join(lines) + '\n'
    if summary:
        with open(summary, 'a', encoding='utf-8') as handle:
            handle.write(text)
    print(text)


if __name__ == '__main__':
    try:
        main()
    except Exception as error:  # a signal must never break the run
        print('::warning title=Skipped tests::The comparison failed: {}'.format(escape_annotation(str(error))))
    sys.exit(0)
