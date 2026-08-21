#!/usr/bin/env bash
# Exercise check-release-assets.sh against canned release listings.
#
# WHY A TEST FOR A THIRTY-LINE SHELL SCRIPT. That script now carries a named
# exclusion for 0.1.2, and an exclusion is only safe while the thing it sits
# inside still fails for everyone else. Nothing about a passing daily audit
# tells you which of those two states you are in: "0 broken" reads identically
# whether the check is discriminating or has quietly gone blind. 0.1.2 itself
# is what eight weeks of an unexamined green look like.
#
# So the failing cases are the point of this file, not the passing one. It
# feeds the script listings it will never see in production - a second
# assetless release, a tag that merely looks like the excluded one, an
# exclusion pointing at a release that is gone - and asserts it still says no.
#
# `gh` is replaced with a stub that serves a fixture and applies the real
# script's own `--jq` filter through real jq, so the draft-vs-published
# selection under test is the one that ships rather than a paraphrase.
#
# Usage: .github/scripts/check-release-assets.test.sh
# Exits 0 when every case behaves, 1 on the first that does not.

set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
subject="${here}/check-release-assets.sh"
[ -x "${subject}" ] || { printf 'not executable: %s\n' "${subject}" >&2; exit 1; }
command -v jq >/dev/null 2>&1 || { printf 'jq is required\n' >&2; exit 1; }

work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT
mkdir -p "${work}/bin"

cat > "${work}/bin/gh" <<'STUB'
#!/usr/bin/env bash
# Stands in for `gh api repos/O/R/releases --paginate --jq EXPR`.
set -euo pipefail
expr=""
while [ $# -gt 0 ]; do
    case "$1" in
        --jq) expr="$2"; shift 2 ;;
        *) shift ;;
    esac
done
[ -n "${expr}" ] || { printf 'stub gh: no --jq given\n' >&2; exit 1; }
# `-c` is not cosmetic: `gh --jq` emits one compact result per line, and
# the subject reads its output line by line. Pretty-printed objects would
# arrive as fragments and fail as malformed JSON rather than as a verdict.
jq -c -r "${expr}" < "${RELEASES_FIXTURE}"
STUB
chmod +x "${work}/bin/gh"
export PATH="${work}/bin:${PATH}"

pass=0
fail=0

# run <name> <expected-exit> <expected-substring-or-empty> [args...]
run() {
    local name="$1" want_rc="$2" want_txt="$3"; shift 3
    local out rc=0
    out="$("${subject}" "$@" 2>&1)" || rc=$?
    if [ "${rc}" != "${want_rc}" ]; then
        printf 'FAIL  %s: exit %s, wanted %s\n%s\n\n' "${name}" "${rc}" "${want_rc}" "${out}" >&2
        fail=$((fail + 1))
        return
    fi
    if [ -n "${want_txt}" ] && ! printf '%s' "${out}" | grep -qF -- "${want_txt}"; then
        printf 'FAIL  %s: output does not mention %s\n%s\n\n' "${name}" "${want_txt}" "${out}" >&2
        fail=$((fail + 1))
        return
    fi
    printf 'ok    %s\n' "${name}"
    pass=$((pass + 1))
}

fixture() {
    RELEASES_FIXTURE="${work}/fixture.json"
    export RELEASES_FIXTURE
    cat > "${RELEASES_FIXTURE}"
}

zipped() { printf '{"tag_name":"%s","draft":false,"published_at":"2026-01-01T00:00:00Z","assets":[{"name":"ShopwareCarve-%s.zip"}]}' "$1" "$1"; }
bare()   { printf '{"tag_name":"%s","draft":%s,"published_at":"2026-01-01T00:00:00Z","assets":[]}' "$1" "${2:-false}"; }

# 1. Production shape: the excluded release is skipped, the rest are installable.
fixture <<< "[$(bare 0.1.2), $(zipped 0.1.1), $(zipped 0.1.0)]"
run 'excluded release passes'            0 'skip  0.1.2'

# 2. THE HALF THAT MATTERS. A different assetless release is still a failure -
#    the exclusion did not turn into "assetless releases are fine".
fixture <<< "[$(bare 0.1.4), $(bare 0.1.2), $(zipped 0.1.1)]"
run 'other assetless release still fails' 1 '0.1.4 is published with no *.zip'

# 3. Two of them: the second is not swallowed by the first.
fixture <<< "[$(bare 0.1.4), $(bare 0.2.0), $(bare 0.1.2)]"
run 'every other assetless release fails' 1 'broken=2'

# 4. A tag that merely looks like the excluded one gets no exemption.
fixture <<< "[$(bare 0.1.20), $(zipped 0.1.1)]"
run 'near-miss tag is not excluded'       1 '0.1.20 is published with no *.zip'

# 5. The exclusion expires with the release it names.
fixture <<< "[$(zipped 0.1.1), $(zipped 0.1.0)]"
run 'stale exclusion fails'               1 'no PUBLISHED release with that tag exists'
# ... and the summary agrees with the verdict rather than reporting broken=0
# above a failing exit, which is what a reader and a dashboard go by.
run 'stale exclusion counts in summary'   1 'broken=0 superseded=0 stale=1'

# 6. An excluded release that later gains its ZIP reports ok, and does NOT read
#    as a stale entry - it is plainly still there.
fixture <<< "[$(zipped 0.1.2), $(zipped 0.1.1)]"
run 'excluded release with a ZIP is ok'   0 'ok    0.1.2'

# 7. Drafts stay out of scope, assetless or not, as before.
fixture <<< "[$(bare 0.1.3 true), $(bare 0.1.2), $(zipped 0.1.1)]"
run 'assetless draft is ignored'          0 'checked=2'

# 8. The pre-existing cannot-fail guard still fires on an empty listing.
fixture <<< "[]"
run 'empty listing is a failure'          1 'no published releases at all'

# 9. Scoped runs: the excluded tag passes, a different assetless tag does not,
#    and neither drags in the stale-entry check for releases out of scope.
fixture <<< "[$(bare 0.1.4), $(bare 0.1.2), $(zipped 0.1.1)]"
run '--tag on the excluded release'       0 'skip  0.1.2' --tag 0.1.2
run '--tag on an assetless release'       1 '0.1.4 is published with no *.zip' --tag 0.1.4
run '--tag on a healthy release'          0 'ok    0.1.1' --tag 0.1.1

printf '\npassed=%s failed=%s\n' "${pass}" "${fail}"
[ "${fail}" -eq 0 ]
