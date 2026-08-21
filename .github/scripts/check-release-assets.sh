#!/usr/bin/env bash
# Assert that every PUBLISHED release carries an installable ZIP.
#
# WHY THIS EXISTS, and why it is not redundant with the release workflow's own
# ordering. On 2026-08-19 run 32199489828 failed at `Resolve release notes`, so
# `Publish GitHub release with ZIP` and `Upload to Shopware Community Store`
# both skipped. The release object for 0.1.2 existed anyway - it had been
# created by hand ten hours earlier, via the draft-first path RELEASING.md
# documents - so the tag, the page and the notes body were all there and the
# ZIP was not. Nothing said anything for eight weeks, because a release page
# with a tag and a body is indistinguishable from one that shipped. No merchant
# received 0.1.2, including the carve-php 0.1.5 security requirement it carried.
#
# The one bit that separates a release from a non-release is whether an asset is
# attached, and until this script nothing asked. Ordering inside release.yml
# cannot cover it: the workflow does not own the path that created that release
# object, so it can only ever guard the releases it makes itself. This can see
# all of them, including the ones already out there.
#
# DRAFTS ARE EXCLUDED, and that is the correct semantics rather than a
# convenience. A draft with no asset is a release being prepared; a published
# one with no asset is a release that lied. At the time of writing a prepared
# 0.1.3 draft has zero assets, and a check that failed on it would be reporting
# a problem that does not exist - which is how a check gets muted.
#
# Usage:
#   .github/scripts/check-release-assets.sh                  # every published release
#   .github/scripts/check-release-assets.sh --tag 0.1.1      # just that one
#   .github/scripts/check-release-assets.sh --repo o/r --pattern '*.zip'
#
# Exits 0 when every published release in scope carries a matching asset,
# 1 when any does not, and 1 when there is nothing to check - see below.

set -euo pipefail

repo="${GITHUB_REPOSITORY:-markup-carve/shopware-carve}"
tag=""
pattern='*.zip'

while [ $# -gt 0 ]; do
    case "$1" in
        --repo) repo="$2"; shift 2 ;;
        --tag) tag="$2"; shift 2 ;;
        --pattern) pattern="$2"; shift 2 ;;
        -h|--help) sed -n '2,30p' "$0"; exit 0 ;;
        *) printf 'check-release-assets: unknown argument %s\n' "$1" >&2; exit 64 ;;
    esac
done

command -v gh >/dev/null 2>&1 || {
    printf '::error::the gh CLI is required and is not on PATH\n' >&2
    exit 1
}

# `--paginate` matters: the API returns 30 releases a page, so without it this
# would silently stop asking after the first page and report the rest clean.
# One compact JSON object per line, so the loop below never has to re-query.
releases="$(gh api "repos/${repo}/releases" --paginate --jq \
    '.[] | select(.draft == false) | {tag_name, published_at, assets: [.assets[].name]}')"

if [ -n "${tag}" ]; then
    releases="$(printf '%s\n' "${releases}" \
        | jq -c --arg t "${tag}" 'select(.tag_name == $t)')"
fi

# A CHECK THAT CANNOT FAIL IS WORSE THAN NO CHECK. An empty listing - a renamed
# repo, a token without the scope, a `--tag` naming a release that is still a
# draft or does not exist - would otherwise walk the loop zero times, find zero
# problems and report success, which is the exact shape of silence this whole
# script exists to remove.
if [ -z "${releases}" ]; then
    if [ -n "${tag}" ]; then
        printf '::error::%s has no PUBLISHED release tagged %s. Either the tag is wrong or that release is still a draft; either way nothing was checked.\n' "${repo}" "${tag}" >&2
    else
        printf '::error::%s reports no published releases at all. That is a broken query, not a clean result - nothing was checked.\n' "${repo}" >&2
    fi
    exit 1
fi

checked=0
broken=0

while IFS= read -r line; do
    [ -n "${line}" ] || continue
    checked=$((checked + 1))
    tag_name="$(printf '%s' "${line}" | jq -r '.tag_name')"
    published="$(printf '%s' "${line}" | jq -r '.published_at')"
    names="$(printf '%s' "${line}" | jq -r '.assets[]?')"

    matched=""
    while IFS= read -r name; do
        [ -n "${name}" ] || continue
        # shellcheck disable=SC2254 # $pattern is a glob on purpose.
        case "${name}" in
            ${pattern}) matched="${matched}${matched:+, }${name}" ;;
        esac
    done <<< "${names}"

    if [ -n "${matched}" ]; then
        printf 'ok    %-8s %s  %s\n' "${tag_name}" "${published}" "${matched}"
        continue
    fi

    broken=$((broken + 1))
    total="$(printf '%s' "${line}" | jq -r '.assets | length')"
    printf 'BROKEN %-7s %s  %s asset(s), none matching %s\n' \
        "${tag_name}" "${published}" "${total}" "${pattern}"
    printf '::error::%s %s is published with no %s attached. It has a tag, a page and a body, and nobody can install it. Either attach the artifact (re-run the release workflow for that tag) or unpublish the release - a published release that ships nothing is indistinguishable from one that shipped.\n' \
        "${repo}" "${tag_name}" "${pattern}" >&2
done <<< "${releases}"

printf '\nchecked=%s broken=%s\n' "${checked}" "${broken}"

[ "${broken}" -eq 0 ] || exit 1
