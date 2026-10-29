#!/usr/bin/env bash
#
# Publishes subdirectories of this repository to their downstream repositories
# (thelia/core, thelia/setup, thelia/config), which Packagist serves.
#
# A published commit is never rewritten again. Each run looks for the commit of
# the branch that the downstream head was made from, rewrites only the commits
# that came after it with `git filter-repo --subdirectory-filter`, and grafts
# them onto the downstream head. A branch that does not exist downstream yet
# starts from the published commit of main it was cut from.
#
# Rewriting the whole history on every run is not stable: filter-repo replaces
# a commit id quoted in a message ("cherry picked from commit ...") only when it
# has already rewritten the quoted commit, so the id of a published commit
# changes with the order in which the branches and tags are walked. The grafted
# commits keep the ids quoted in their messages unchanged, those of this
# repository.
#
# A push is never forced and never carries tags: release tags are created on the
# downstream repositories by the release procedure.
#
# Usage: split-downstream.sh <bare-source-clone> <branch>...
#
# Environment:
#   SPLIT_MODE     "check" (default): rewrite and compare, push nothing.
#                  "dry-run": also authenticate and run `git push --dry-run`.
#                  "publish": push.
#   SPLIT_KEY_DIR  directory holding one SSH private key per component (core,
#                  setup, config); required by "dry-run" and "publish".
#   SPLIT_KNOWN_HOSTS  known_hosts file pinning github.com; same requirement.

set -euo pipefail

# component:subdirectory:repository:branches published downstream
readonly COMPONENTS=(
    'core:core:thelia/core:main 3.1 3.2 3.3'
    'setup:setup:thelia/setup:main 3.1 3.2 3.3'
    'config:local/config:thelia/config:main'
)

# Branches this script may publish
readonly MAINTAINED_BRANCHES=(main 3.1 3.2 3.3)

readonly mode="${SPLIT_MODE:-check}"
readonly workspace="$(mktemp -d)"
trap 'rm -rf "$workspace"' EXIT

fail() {
    echo "::error::$*" >&2
    exit 1
}

[[ $# -ge 2 ]] || fail "usage: $0 <bare-source-clone> <branch>..."
readonly source="$(cd "$1" && pwd)"
shift

case "$mode" in
    check) ;;
    dry-run|publish)
        [[ -d "${SPLIT_KEY_DIR:-}" ]] || fail 'SPLIT_KEY_DIR is not a directory'
        [[ -f "${SPLIT_KNOWN_HOSTS:-}" ]] || fail 'SPLIT_KNOWN_HOSTS is not a file'
        ;;
    *) fail "unknown SPLIT_MODE: $mode" ;;
esac

command -v git-filter-repo >/dev/null || fail 'git-filter-repo is not installed'
# A shallow history hides the commits the downstream head was made from
[[ "$(git -C "$source" rev-parse --is-shallow-repository)" == false ]] \
    || fail "$source is a shallow clone, the full history is required"

# What the rewrite keeps of a commit: the tree of the subdirectory, the author
# and the committer, dates included. The message is left out, its quoted commit
# ids may have been rewritten.
readonly IDENTITY_FORMAT='%an%x1f%ae%x1f%ad%x1f%cn%x1f%ce%x1f%cd'

# Prints "<commit of the branch> <published commit made from it>" for the most
# recent commit of the branch found in the published history.
find_base() {
    local work="$1" subdirectory="$2" branch="$3" published_ref="$4"

    local -A published_commits=()
    local commit fingerprint
    while IFS=$'\x1f' read -r commit fingerprint; do
        published_commits["$fingerprint"]="$commit"
    done < <(git -C "$work" log --date=raw --format="%H%x1f%T%x1f$IDENTITY_FORMAT" "$published_ref")

    while read -r commit; do
        fingerprint="$(git -C "$work" rev-parse "$commit:$subdirectory")"$'\x1f'
        fingerprint+="$(git -C "$work" log -1 --date=raw --format="$IDENTITY_FORMAT" "$commit")"
        if [[ -n "${published_commits[$fingerprint]:-}" ]]; then
            echo "$commit ${published_commits[$fingerprint]}"
            return
        fi
    done < <(git -C "$work" rev-list "refs/heads/$branch" -- "$subdirectory")
}

# Rewrites the commits of the branch that came after $base and grafts them onto
# $published, which was made from $base.
graft() {
    local work="$1" subdirectory="$2" branch="$3" base="$4" published="$5"

    if [[ -z "$(git -C "$work" rev-list -1 "$base..refs/heads/$branch" -- "$subdirectory")" ]]; then
        git -C "$work" update-ref "refs/heads/$branch" "$published"
        return
    fi

    # Outside the range, only $base may be a parent (a pruned commit hands its
    # grafted parent over to its child): any other one would drag the history
    # of this whole repository downstream.
    git -C "$work" filter-repo --force --quiet --prune-empty always \
        --subdirectory-filter "$subdirectory" --preserve-commit-hashes \
        --commit-callback "
for index, parent in enumerate(commit.parents):
    if parent == b'$base':
        commit.parents[index] = b'$published'
    elif isinstance(parent, bytes) and parent != b'$published':
        raise SystemExit('parent ' + parent.decode() + ' is outside the rewritten range')
" \
        --refs "^$base" "refs/heads/$branch"
}

publish() {
    local name="$1" subdirectory="$2" repository="$3" branch="$4"
    local work="$workspace/$name-$branch"

    echo "::group::$subdirectory ($branch) -> $repository"

    git clone --quiet --bare --no-local "$source" "$work"

    local remote lineage="$branch"
    remote="$(git ls-remote "https://github.com/$repository.git" "refs/heads/$branch" | cut -f1)"
    [[ -n "$remote" ]] || lineage=main
    git -C "$work" fetch --quiet --no-tags "https://github.com/$repository.git" \
        "+refs/heads/$lineage:refs/published/$lineage"
    if [[ -n "$remote" && "$(git -C "$work" rev-parse "refs/published/$lineage")" != "$remote" ]]; then
        fail "$repository $branch: the downstream branch moved during the run"
    fi

    local base published
    read -r base published < <(find_base "$work" "$subdirectory" "$branch" "refs/published/$lineage") || true
    [[ -n "${base:-}" ]] \
        || fail "$repository $branch: no commit of $branch is published on $lineage downstream"
    if [[ -n "$remote" && "$published" != "$remote" ]]; then
        fail "$repository $branch: downstream $remote was not made from a commit of $branch; refusing to rewrite published history"
    fi
    echo "$repository $branch: $published was made from $base"

    graft "$work" "$subdirectory" "$branch" "$base" "$published" \
        || fail "$repository $branch: the rewrite of the commits after $base failed"

    local head expected_tree actual_tree
    head="$(git -C "$work" rev-parse "refs/heads/$branch")"
    expected_tree="$(git -C "$source" rev-parse "refs/heads/$branch:$subdirectory")"
    actual_tree="$(git -C "$work" rev-parse "refs/heads/$branch^{tree}")"
    [[ "$actual_tree" == "$expected_tree" ]] \
        || fail "$repository $branch: rewritten tree $actual_tree differs from $subdirectory ($expected_tree)"

    if [[ "$remote" == "$head" ]]; then
        echo "$repository $branch: up to date ($head)"
        [[ "$mode" != dry-run ]] || push "$name" "$repository" "$branch"
        echo '::endgroup::'
        return
    fi

    if [[ -z "$remote" ]]; then
        echo "$repository $branch: branch does not exist downstream, it will be created at $head"
    elif ! git -C "$work" merge-base --is-ancestor "$remote" "$head"; then
        fail "$repository $branch: downstream $remote is not an ancestor of $head; refusing to rewrite published history"
    else
        echo "$repository $branch: $remote..$head, $(git -C "$work" rev-list --count "$remote..$head") new commit(s)"
    fi

    [[ "$mode" == check ]] || push "$name" "$repository" "$branch"

    echo '::endgroup::'
}

# In dry-run mode the push is simulated, which still checks that the deploy key
# can write to the repository.
push() {
    local name="$1" repository="$2" branch="$3"
    local key="$SPLIT_KEY_DIR/$name"
    [[ -f "$key" ]] || fail "no deploy key for $name"
    local push_options=()
    [[ "$mode" == dry-run ]] && push_options+=(--dry-run)
    GIT_SSH_COMMAND="ssh -i $key -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=$SPLIT_KNOWN_HOSTS" \
        git -C "$workspace/$name-$branch" push --no-tags "${push_options[@]}" \
        "git@github.com:$repository.git" "refs/heads/$branch:refs/heads/$branch"
}

for branch in "$@"; do
    [[ " ${MAINTAINED_BRANCHES[*]} " == *" $branch "* ]] || fail "branch $branch is not maintained"
done
for branch in "${MAINTAINED_BRANCHES[@]}"; do
    git -C "$source" rev-parse --verify --quiet "refs/heads/$branch" >/dev/null \
        || fail "branch $branch does not exist in $source"
done

for component in "${COMPONENTS[@]}"; do
    IFS=':' read -r name subdirectory repository branches <<< "$component"
    for branch in "$@"; do
        [[ " $branches " == *" $branch "* ]] || continue
        publish "$name" "$subdirectory" "$repository" "$branch"
    done
done

# A new maintenance branch is never published until it is added to
# MAINTAINED_BRANCHES (and to split.yml): say so, and fail when asked to.
mapfile -t unpublished < <(git -C "$source" for-each-ref --format='%(refname:lstrip=2)' refs/heads \
    | grep -E '^([3-9]|[1-9][0-9])\.[0-9]+$' \
    | grep -vxF -f <(printf '%s\n' "${MAINTAINED_BRANCHES[@]}") || true)
if [[ ${#unpublished[@]} -gt 0 ]]; then
    message="maintenance branch(es) ${unpublished[*]} not published: add them to MAINTAINED_BRANCHES and to split.yml"
    [[ "${SPLIT_REQUIRE_KNOWN_BRANCHES:-}" != 1 ]] || fail "$message"
    echo "::warning::$message"
fi
