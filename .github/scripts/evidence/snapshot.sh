#!/usr/bin/env bash
#
# Writes the public surface the application exposes at runtime, as JSON, into
# <directory>: the Twig functions, filters, tests and globals, the routes, the
# event listeners and the OpenAPI description of the API. Run once on the pull
# request and once on the base code, the two directories are compared by
# surface-diff.php and oasdiff.
#
# A command that fails leaves its error output next to the missing file; the
# comparison reports it instead of reading an empty surface as "everything was
# removed".
#
# Usage: .github/scripts/evidence/snapshot.sh <directory>

set -uo pipefail

directory="${1:?Usage: snapshot.sh <directory>}"
mkdir -p "$directory"
rm -rf var/cache/test

console() {
    php bin/console "$@" --env=test --no-debug
}

capture() {
    local name="$1"
    shift
    if console "$@" > "$directory/$name.json" 2> "$directory/$name.err" && jq -e . "$directory/$name.json" > /dev/null 2>&1; then
        echo "$name: $(wc -c < "$directory/$name.json") bytes"
    else
        echo "::warning title=Surface snapshot failed::bin/console $* failed in $directory: $(head -c 300 "$directory/$name.err")"
        rm -f "$directory/$name.json"
    fi
}

capture twig debug:twig --format=json
capture routes debug:router --format=json
capture events debug:event-dispatcher --format=json
capture openapi api:openapi:export
