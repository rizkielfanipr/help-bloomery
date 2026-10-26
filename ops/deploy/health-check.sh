#!/usr/bin/env bash

set -Eeuo pipefail

if (( $# != 2 )); then
    echo 'Usage: health-check.sh <release-path> <health-url>' >&2
    exit 64
fi

readonly release_path="$(realpath -e "$1")"
readonly health_url="$2"
readonly php_binary="${DEPLOY_PHP_BINARY:-/usr/bin/php}"
readonly curl_binary="${DEPLOY_CURL_BINARY:-/usr/bin/curl}"

if [[ ! -f "${release_path}/artisan" ]]; then
    echo 'Release path does not contain artisan.' >&2
    exit 66
fi

if [[ ! "$health_url" =~ ^https?:// ]]; then
    echo 'Health URL must use HTTP or HTTPS.' >&2
    exit 64
fi

if [[ ! -f "${release_path}/public/build/manifest.json" ]]; then
    echo 'Vite manifest is missing from the release.' >&2
    exit 66
fi

(
    cd "$release_path"
    "$php_binary" artisan migrate:status --no-interaction >/dev/null
    "$php_binary" artisan app:check-redis --no-interaction
)

"$curl_binary" \
    --fail \
    --silent \
    --show-error \
    --location \
    --retry 5 \
    --retry-delay 5 \
    --retry-all-errors \
    --connect-timeout 5 \
    --max-time 10 \
    "$health_url" >/dev/null

echo 'Release health check passed.'
