#!/usr/bin/env bash
#
# Publishes subdirectories of this repository to their downstream repositories
# (thelia/core, thelia/setup, thelia/config), which Packagist serves.
#
# The history is rewritten with `git filter-repo --subdirectory-filter`, the
# same command the former manual extractor ran, so the published commits keep
# the SHAs they already have downstream. A push is never forced and never
# carries tags: release tags are created on the downstream repositories by the
# release procedure.
#
# filter-repo also rewrites the commit ids quoted in messages ("cherry picked
# from commit ...") when the quoted commit belongs to the rewritten history, so
# a commit's new id depends on which refs are rewritten together. Every run
# therefore rewrites the same set: all the maintained branches and every tag,
# which is what the manual extractor did.
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
    'core:core:thelia/core:main 3.1 3.2'
    'setup:setup:thelia/setup:main 3.1 3.2'
    'config:local/config:thelia/config:main'
)

# Branches rewritten together on every run (see above), published or not
readonly MAINTAINED_BRANCHES=(main 3.1 3.2)

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
# A shallow history rewrites into orphan commits with other ids
[[ "$(git -C "$source" rev-parse --is-shallow-repository)" == false ]] \
    || fail "$source is a shallow clone, the full history is required"

rewrite() {
    local name="$1" subdirectory="$2"
    local work="$workspace/$name"

    git clone --quiet --bare --no-local "$source" "$work"
    local refs=() branch
    for branch in "${MAINTAINED_BRANCHES[@]}"; do
        refs+=("refs/heads/$branch")
    done
    mapfile -t -O "${#refs[@]}" refs < <(git -C "$work" for-each-ref --format='%(refname)' refs/tags)
    git -C "$work" filter-repo --force --quiet --prune-empty always \
        --subdirectory-filter "$subdirectory" --refs "${refs[@]}"
}

publish() {
    local name="$1" subdirectory="$2" repository="$3" branch="$4"
    local work="$workspace/$name"

    echo "::group::$subdirectory ($branch) -> $repository"

    local head expected_tree actual_tree
    head="$(git -C "$work" rev-parse "refs/heads/$branch")"
    expected_tree="$(git -C "$source" rev-parse "refs/heads/$branch:$subdirectory")"
    actual_tree="$(git -C "$work" rev-parse "refs/heads/$branch^{tree}")"
    [[ "$actual_tree" == "$expected_tree" ]] \
        || fail "$repository $branch: rewritten tree $actual_tree differs from $subdirectory ($expected_tree)"

    local remote
    remote="$(git ls-remote "https://github.com/$repository.git" "refs/heads/$branch" | cut -f1)"

    if [[ "$remote" == "$head" ]]; then
        echo "$repository $branch: up to date ($head)"
        [[ "$mode" != dry-run ]] || push "$name" "$repository" "$branch"
        echo '::endgroup::'
        return
    fi

    if [[ -n "$remote" ]] && git -C "$work" cat-file -e "$remote^{commit}" 2>/dev/null \
        && git -C "$work" merge-base --is-ancestor "$head" "$remote"; then
        echo "$repository $branch: downstream $remote already contains $head"
        echo '::endgroup::'
        return
    fi

    if [[ -z "$remote" ]]; then
        echo "$repository $branch: branch does not exist downstream, it will be created at $head"
    elif ! git -C "$work" cat-file -e "$remote^{commit}" 2>/dev/null \
        || ! git -C "$work" merge-base --is-ancestor "$remote" "$head"; then
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
        git -C "$workspace/$name" push --no-tags "${push_options[@]}" \
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
    rewritten=0
    for branch in "$@"; do
        [[ " $branches " == *" $branch "* ]] || continue
        if [[ $rewritten == 0 ]]; then
            rewrite "$name" "$subdirectory"
            rewritten=1
        fi
        publish "$name" "$subdirectory" "$repository" "$branch"
    done
done
