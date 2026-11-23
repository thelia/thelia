#!/usr/bin/env bash
# Fails when a change touches the update script of a version that is already tagged.
#
# The updater runs the scripts of the versions above the one a shop is on, so a shop already on
# 3.4.0 never runs setup/update/sql/3.4.0.sql again: a line added there after the 3.4.0 release
# reaches fresh installs of nothing and updated shops of nothing. It belongs in the script of the
# version in development.
#
# Usage: published-update-scripts.sh <base-ref>   (the tags must be fetched)
set -euo pipefail

base="${1:?usage: published-update-scripts.sh <base-ref>}"
from="$(git merge-base "$base" HEAD)"
found=0

while IFS=$'\t' read -r status path renamed_to; do
  # A rename (R) or copy (C) touches its source as much as its target.
  for file in "$path" ${renamed_to:+"$renamed_to"}; do
    version="$(basename "$file")"
    version="${version%.*}"
    if git rev-parse -q --verify "refs/tags/$version" >/dev/null; then
      found=1
      echo "::error file=$file,title=Published update script::$file belongs to $version, already released: a shop on $version or later never runs it again. Put the change in the update script of the version in development."
      echo "$status $file: version $version is tagged"
    fi
  done
done < <(git diff --name-status "$from" HEAD -- setup/update/sql setup/update/php setup/update/instruction)

if [ "$found" -eq 0 ]; then
  echo "No update script of a released version changed."
fi

exit "$found"
