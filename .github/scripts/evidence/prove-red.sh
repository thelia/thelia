#!/usr/bin/env bash
#
# A test only proves something if it fails on the code as it was before the
# change it comes with. Run after revert-to-base.sh, this script runs each test
# file the pull request adds or changes against the base code and records how
# it ends:
#
#   red        at least one assertion fails: the test catches the change;
#   error      it errors without a failing assertion (a missing class, a fatal
#              error): it depends on the change, but nothing says it checks it;
#   green      it passes on the base code too: it would not catch a regression
#              of this change;
#   skipped    every test of the file was skipped;
#   not-run    PHPUnit produced no result.
#
# When the pull request changes nothing but tests, there is nothing to revert
# and every test is reported as test-only.
#
# Usage: .github/scripts/evidence/prove-red.sh <base-ref> <reverted-paths.txt> <output.json>

set -uo pipefail

base="${1:?Usage: prove-red.sh <base-ref> <reverted-paths.txt> <output.json>}"
reverted="${2:?}"
output="${3:?}"
workdir="$(mktemp -d)"

mapfile -t tests < <(git diff --name-only --diff-filter=AMR "$base" HEAD -- tests/ | grep -E 'Test\.php$' || true)

entries=()
for test in "${tests[@]}"; do
    if [ ! -s "$reverted" ]; then
        entries+=("$(jq -nc --arg test "$test" '{test: $test, status: "test-only"}')")
        continue
    fi

    junit="$workdir/$(echo "$test" | tr '/' '_').xml"
    log="$workdir/$(echo "$test" | tr '/' '_').log"
    timeout 300 ./vendor/bin/phpunit --log-junit "$junit" "$test" > "$log" 2>&1
    code=$?

    if [ -s "$junit" ]; then
        # shellcheck disable=SC2016 # PHP code, expanded by PHP
        read -r count failures errors skipped < <(php -r '
            $suite = simplexml_load_file($argv[1])->testsuite;
            echo (int) $suite["tests"], " ", (int) $suite["failures"], " ", (int) $suite["errors"], " ", (int) $suite["skipped"], "\n";
        ' "$junit")
    else
        count=0; failures=0; errors=0; skipped=0
    fi

    if [ "$failures" -gt 0 ]; then
        status=red
    elif [ "$errors" -gt 0 ]; then
        status=error
    elif [ "$count" -eq 0 ]; then
        status=not-run
    elif [ "$skipped" -eq "$count" ]; then
        status=skipped
    else
        status=green
    fi

    echo "$test: $status (tests $count, failures $failures, errors $errors, skipped $skipped, exit $code)"
    if [ "$status" = green ]; then
        echo "::warning file=$test,title=Test never red::This test passes on the code before the pull request too: it does not catch a regression of this change."
    fi

    entries+=("$(jq -nc --arg test "$test" --arg status "$status" \
        --argjson tests "$count" --argjson failures "$failures" \
        --argjson errors "$errors" --argjson skipped "$skipped" \
        --arg tail "$(tail -n 15 "$log")" \
        '{test: $test, status: $status, tests: $tests, failures: $failures, errors: $errors, skipped: $skipped, output_tail: $tail}')")
done

printf '%s\n' "${entries[@]}" | jq -s \
    --argjson reverted "$(jq -R . "$reverted" | jq -s .)" \
    '{reverted_files: $reverted, tests: map(select(. != null))}' > "$output"
