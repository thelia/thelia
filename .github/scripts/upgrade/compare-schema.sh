#!/usr/bin/env bash
# Checks the schema of a database moved up by setup/update.php, from the SQL that
# `thelia:dev:db:diff` generates for it and for a fresh install of the same code.
#
# The command measures a database against the Propel schema (local/config/schema.xml), and even a
# fresh install is not at zero against it: a few thousand lines of timestamp types and foreign key
# names. Two exact checks stand on that output:
#
#   1. The fresh install lacks no column and no table of schema.xml. The fresh install is built
#      from setup/thelia.sql: a column added to schema.xml and not to thelia.sql shows up as an
#      `ADD` in the diff of the fresh install, where it should never be.
#   2. The updated database has the structure of the fresh install. Both outputs go through the
#      same reverse engineering, so the noise cancels, and any line left is a structure the update
#      scripts did not bring (a column, an index, a foreign key).
#
# Usage: compare-schema.sh <diff-of-fresh-install.sql> <diff-of-updated-database.sql>
# Exit:  0 both checks pass · 1 one fails · 2 an input is missing or is not a schema diff
set -euo pipefail

fresh="${1:?path of the diff generated on the fresh install}"
updated="${2:?path of the diff generated on the updated database}"
summary="${GITHUB_STEP_SUMMARY:-/dev/null}"
max_lines=200
status=0

for file in "$fresh" "$updated"; do
  if [ ! -s "$file" ] || ! grep -q '^-- DATABASE .*, UP$' "$file"; then
    echo "::error::$file is not the output of thelia:dev:db:diff"
    head -c 1500 "$file" 2>/dev/null || true
    exit 2
  fi
done

echo "fresh install   : $(wc -l < "$fresh") lines"
echo "updated database: $(wc -l < "$updated") lines"

# The statements that turn the database into the schema, without the ones that undo it.
up_section() { sed '/^-- DATABASE .*, DOWN$/,$d' "$1"; }

# Check 1. `api` is a table of thelia.sql that schema.xml does not describe: the command drops and
# recreates it on every database, fresh or not.
if missing=$(up_section "$fresh" | grep -nE '(^|[[:space:]])ADD `|^CREATE TABLE `' | grep -v 'CREATE TABLE `api`'); then
  status=1
  {
    echo '### Fresh install: setup/thelia.sql is behind local/config/schema.xml'
    echo
    echo 'A fresh install lacks what `schema.xml` describes. `thelia:dev:db:diff` wants to run these statements on it:'
    echo
    echo '```sql'
    printf '%s\n' "$missing" | head -n "$max_lines"
    echo '```'
  } >> "$summary"
  printf '%s\n' "$missing" | head -n "$max_lines"
  echo '::error::a fresh install lacks a column or a table of schema.xml: setup/thelia.sql is behind it'
fi

# Check 2.
if delta=$(diff -u --label 'fresh install' --label 'updated database' "$fresh" "$updated"); then
  echo 'The updated database has the structure of a fresh install.'
  if [ "$status" -eq 0 ]; then
    {
      echo '### Update path: the updated database matches a fresh install'
      echo
      echo "Compared \`thelia:dev:db:diff\` of both databases ($(wc -l < "$fresh") lines each, identical)."
    } >> "$summary"
  fi
else
  status=1
  total=$(printf '%s\n' "$delta" | wc -l)
  {
    echo '### Update path: the updated database differs from a fresh install'
    echo
    echo 'Lines prefixed with `+` are statements `thelia:dev:db:diff` still wants to run on the updated database, and not on a fresh install: the update scripts did not bring that part of the structure.'
    echo
    echo '```diff'
    printf '%s\n' "$delta" | head -n "$max_lines"
    echo '```'
    if [ "$total" -gt "$max_lines" ]; then
      echo
      echo "Showing $max_lines of $total lines; the full diffs are in the \`upgrade-diffs\` artifact."
    fi
  } >> "$summary"
  printf '%s\n' "$delta" | head -n "$max_lines"
  echo "::error::the updated database does not have the structure of a fresh install ($total diff lines)"
fi

exit "$status"
