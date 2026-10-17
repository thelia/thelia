#!/usr/bin/env bash
#
# Runs Roave BackwardCompatibilityCheck on the PHP API of core/ only, between
# the base and the head of a pull request.
#
# The tool compares the autoload paths of the composer.json at the root of a
# git repository, and installs the dependencies of both revisions. The root
# composer.json of this repository is the development monorepo (themes, local
# modules); the package shops depend on is core/ (thelia/core). The script
# builds a throwaway repository holding core/ at both revisions and runs the
# tool there.
#
# The Propel base classes (Thelia\Model\Base, Thelia\Model\Map) are generated at
# install time and are not in git: every reference to them is reported as "could
# not be found in the located source". Those lines are left out of the result.
#
# Usage: .github/scripts/evidence/roave-core.sh <base-ref> <head-ref> <output.json>

set -uo pipefail

base="${1:?Usage: roave-core.sh <base-ref> <head-ref> <output.json>}"
head="${2:?}"
output="$(realpath -m "${3:?}")"
source_repository="$(pwd)"
workdir="$(mktemp -d)"

snapshot() {
    find "$workdir" -mindepth 1 -maxdepth 1 ! -name .git -exec rm -rf {} +
    git -C "$source_repository" archive "$1:core" | tar -x -C "$workdir"
    git -C "$workdir" add --all
    git -C "$workdir" -c user.name=ci -c user.email=ci@localhost commit --quiet --allow-empty -m "$2"
}

git init --quiet "$workdir"
snapshot "$base" base
snapshot "$head" head

cd "$workdir" || exit 1
roave-backward-compatibility-check --from=HEAD~1 --to=HEAD --format=markdown > "$workdir.out" 2> "$workdir.err"
code=$?

grep -E '^ *- \[BC\]' "$workdir.out" | sed -E 's/^ *- \[BC\] //' | grep -v 'could not be found in the located source' | awk '!seen[$0]++' > "$workdir.breaks" || true
noise=$(grep -c 'could not be found in the located source' "$workdir.out" || true)

# 0: no break, 3: breaks found. Anything else is the tool failing.
if [ "$code" -ne 0 ] && [ "$code" -ne 3 ]; then
    echo "::warning title=BC check failed::roave-backward-compatibility-check exited with $code: $(tail -c 500 "$workdir.err")"
    jq -n --argjson code "$code" --arg error "$(tail -c 2000 "$workdir.err")" \
        '{ran: false, exit_code: $code, error: $error}' > "$output"
    exit 0
fi

while IFS= read -r line; do
    echo "::warning title=Backward compatibility break in core::$line"
done < <(head -n 30 "$workdir.breaks")

jq -n --argjson code "$code" --argjson ignored "${noise:-0}" \
    --argjson breaks "$(jq -R . "$workdir.breaks" | jq -s .)" \
    '{ran: true, exit_code: $code, breaks: $breaks, ignored_generated_class_lines: $ignored}' > "$output"
echo "$(wc -l < "$workdir.breaks") break(s) in core, ${noise:-0} line(s) about generated classes left out"
