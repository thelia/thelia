#!/usr/bin/env bash
#
# Puts every file the pull request changes back to its base version, except the
# tests and the CI files. What is left is the code as it was before the pull
# request, with the tests of the pull request: a new test run against it shows
# whether it would have caught the change it comes with.
#
# Only the working tree changes; nothing is committed. Files the pull request
# adds are deleted, files it deletes or renames come back under their old path.
# The Composer dependencies stay those of the pull request.
#
# Usage: .github/scripts/evidence/revert-to-base.sh <base-ref>
# Prints the reverted paths, one per line.

set -euo pipefail

base="${1:?Usage: revert-to-base.sh <base-ref>}"

kept() {
    case "$1" in
        tests/*|.github/*) return 0 ;;
        *) return 1 ;;
    esac
}

restore() {
    git checkout --quiet "$base" -- "$1"
    echo "$1"
}

remove() {
    rm -f -- "$1"
    echo "$1"
}

while IFS=$'\t' read -r status first second; do
    case "$status" in
        A*|C*)
            path="${second:-$first}"
            kept "$path" || remove "$path"
            ;;
        M*|D*|T*)
            kept "$first" || restore "$first"
            ;;
        R*)
            kept "$second" || remove "$second"
            kept "$first" || restore "$first"
            ;;
    esac
done < <(git diff --name-status -M "$base" HEAD)
