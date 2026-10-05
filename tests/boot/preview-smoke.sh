#!/usr/bin/env bash
# Posts a document to the admin preview endpoint of a booted shop, once per
# environment. Run from the shop root; the plugin checkout supplies this file.
#
# prod and dev both, because the container differs: the preview controller
# takes Shopware's own translator, and `translator` resolves to Symfony's
# DataCollectorTranslator once debug decorators are in. That mismatch answers
# 500 on the first real request and is invisible to a test that builds the
# controller by hand.
set -euo pipefail

host='127.0.0.1'
port='8000'
base="http://${host}:${port}"
user="${ADMIN_USER:-admin}"
password="${ADMIN_PASSWORD:-shopware}"

fail() {
    echo "::error::$1"
    exit 1
}

for env in prod dev; do
    echo "--- ${env}"
    APP_ENV="${env}" php -S "${host}:${port}" -t public public/index.php \
        >"/tmp/server-${env}.log" 2>&1 &
    server=$!
    trap 'kill "${server}" 2>/dev/null || true' EXIT

    up=''
    for _ in $(seq 1 60); do
        code="$(curl -s -o /dev/null -w '%{http_code}' "${base}/api/_info/version" || true)"
        # 401 is the shop answering: the endpoint exists and wants a token.
        if [ "${code}" = '401' ] || [ "${code}" = '200' ]; then
            up='yes'
            break
        fi
        if ! kill -0 "${server}" 2>/dev/null; then
            cat "/tmp/server-${env}.log"
            fail "the ${env} server exited before it answered"
        fi
        sleep 2
    done
    [ -n "${up}" ] || { cat "/tmp/server-${env}.log"; fail "the ${env} server never answered on ${base}"; }

    token="$(curl -sf -X POST "${base}/api/oauth/token" \
        -H 'Content-Type: application/json' \
        -d "{\"grant_type\":\"password\",\"client_id\":\"administration\",\"scopes\":\"write\",\"username\":\"${user}\",\"password\":\"${password}\"}" \
        | php -r '$d = json_decode(stream_get_contents(STDIN), true); echo $d["access_token"] ?? "";')"
    [ -n "${token}" ] || { cat "/tmp/server-${env}.log"; fail "no admin API token in ${env}, so nothing below is authenticated"; }

    body="/tmp/preview-${env}.json"
    code="$(curl -s -o "${body}" -w '%{http_code}' -X POST "${base}/api/_action/carve/preview" \
        -H "Authorization: Bearer ${token}" \
        -H 'Content-Type: application/json' \
        -d '{"source":"*bold* and /italic/","includes":false}')"
    echo "POST /api/_action/carve/preview -> ${code}"
    head -c 2000 "${body}"; echo
    if [ "${code}" != '200' ]; then
        tail -n 80 "/tmp/server-${env}.log"
        fail "the preview endpoint answered ${code} in ${env}"
    fi
    if ! grep -q '<strong>bold<\\/strong>\|<strong>bold</strong>' "${body}"; then
        fail "the preview response in ${env} carries no rendered HTML"
    fi

    # The catalog endpoint is a GET through the same controller, so it proves
    # the route resolves to a callable service and not only that it is listed.
    code="$(curl -s -o /dev/null -w '%{http_code}' "${base}/api/_action/carve/includes" \
        -H "Authorization: Bearer ${token}")"
    echo "GET /api/_action/carve/includes -> ${code}"
    case "${code}" in
        200|403) ;;
        *) tail -n 80 "/tmp/server-${env}.log"; fail "the include catalog answered ${code} in ${env}" ;;
    esac

    kill "${server}" 2>/dev/null || true
    wait "${server}" 2>/dev/null || true
    trap - EXIT
done

echo 'preview smoke ok in prod and dev'
