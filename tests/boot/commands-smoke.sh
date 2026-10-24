#!/usr/bin/env bash
# Runs carve:convert, carve:lint and carve:fmt against a booted shop's real
# database, and checks that lintOnSave rejects an admin API write. Run from the
# shop root; the plugin checkout supplies this file.
set -euo pipefail

host='127.0.0.1'
port='8001'
base="http://${host}:${port}"
user="${ADMIN_USER:-admin}"
password="${ADMIN_PASSWORD:-shopware}"
plugin="$(cd "$(dirname "$0")/../.." && pwd)"

fail() {
    echo "::error::$1"
    exit 1
}

APP_ENV=prod APP_DEBUG=0 php -S "${host}:${port}" -t public public/index.php >/tmp/server-commands.log 2>&1 &
server=$!
trap 'kill "${server}" 2>/dev/null || true' EXIT
for _ in $(seq 1 60); do
    code="$(curl -s -o /dev/null -w '%{http_code}' "${base}/api/_info/version" || true)"
    [ "${code}" = '401' ] || [ "${code}" = '200' ] && break
    sleep 2
done
token="$(curl -sf -X POST "${base}/api/oauth/token" \
    -H 'Content-Type: application/json' \
    -d "{\"grant_type\":\"password\",\"client_id\":\"administration\",\"scopes\":\"write\",\"username\":\"${user}\",\"password\":\"${password}\"}" \
    | php -r '$d = json_decode(stream_get_contents(STDIN), true); echo $d["access_token"] ?? "";')"
[ -n "${token}" ] || { cat /tmp/server-commands.log; fail 'no admin API token'; }

id="$(php -r 'echo bin2hex(random_bytes(16));')"
code="$(curl -s -o /tmp/manufacturer.json -w '%{http_code}' -X POST "${base}/api/product-manufacturer" \
    -H "Authorization: Bearer ${token}" -H 'Content-Type: application/json' \
    -d "{\"id\":\"${id}\",\"name\":\"Carve smoke\",\"description\":\"<p>Hi <b>there</b></p>\"}")"
[ "${code}" = '204' ] || { cat /tmp/manufacturer.json; fail "creating a manufacturer answered ${code}"; }

dry="$(php bin/console carve:convert --entity manufacturer)"
echo "${dry}"
case "${dry}" in *'Would convert 1 description(s)'*) ;; *) fail 'carve:convert did not find the stored HTML description' ;; esac

wet="$(php bin/console carve:convert --entity manufacturer --write)"
echo "${wet}"
case "${wet}" in *'Converted 1 description(s)'*) ;; *) fail 'carve:convert --write did not store the conversion' ;; esac

again="$(php bin/console carve:convert --entity manufacturer)"
case "${again}" in *'skipped 1 with existing Carve'*) ;; *) echo "${again}"; fail 'the stored Carve source was not read back' ;; esac

php bin/console carve:lint --entity manufacturer || fail 'carve:lint reported the converted source'

printf '| a | bbb |\n|---|---|\n| cc | d |\n' > /tmp/smoke-table.crv
formatted="$(php bin/console carve:fmt --align-tables /tmp/smoke-table.crv)"
case "${formatted}" in *'| a  | bbb |'*) ;; *) echo "${formatted}"; fail 'carve:fmt --align-tables did not align' ;; esac

php bin/console system:config:set ShopwareCarve.config.lintOnSave true
code="$(curl -s -o /tmp/gate.json -w '%{http_code}' -X PATCH "${base}/api/product-manufacturer/${id}" \
    -H "Authorization: Bearer ${token}" -H 'Content-Type: application/json' \
    -d '{"customFields":{"carve_manufacturer_body":"**bold**"}}')"
head -c 1000 /tmp/gate.json; echo
[ "${code}" = '400' ] || fail "lintOnSave let a write with findings through (${code})"
grep -q 'markdown-strong-double-star' /tmp/gate.json || fail 'the rejection does not name the lint rule'

lint_status=0
php bin/console carve:lint --entity manufacturer || lint_status=$?
[ "${lint_status}" = '0' ] || fail 'the rejected write changed the stored source'

echo 'command smoke ok'
