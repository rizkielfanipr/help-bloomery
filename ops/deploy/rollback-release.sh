#!/usr/bin/env bash

set -Eeuo pipefail
umask 002

if (( $# != 2 )); then
    echo 'Usage: rollback-release.sh <environment> <release-name>' >&2
    exit 64
fi

readonly environment_name="$1"
readonly release_name="$2"
readonly deployment_root="${DEPLOY_ROOT:-/var/www/bloomery}"
readonly base_path="${deployment_root}/${environment_name}"
readonly releases_path="${base_path}/releases"
readonly current_path="${base_path}/current"
readonly target_path="${releases_path}/${release_name}"
readonly health_check_script="${DEPLOY_HEALTH_CHECK_SCRIPT:-/home/deploy/bin/bloomery-release/health-check.sh}"
readonly php_binary="${DEPLOY_PHP_BINARY:-/usr/bin/php}"
readonly health_url="${APP_HEALTH_URL:-}"

case "$environment_name" in
    staging|production) ;;
    *) echo 'Environment must be staging or production.' >&2; exit 64 ;;
esac

if [[ ! "$release_name" =~ ^[1-9][0-9]*-[0-9a-f]{8}$ ]]; then
    echo 'Release name must use <run-number>-<8-character-sha>.' >&2
    exit 64
fi

if [[ -z "$health_url" || ! -d "$target_path" || ! -f "${target_path}/artisan" ]]; then
    echo 'Rollback target or APP_HEALTH_URL is invalid.' >&2
    exit 66
fi

exec 9>"${base_path}/.deploy.lock"

if ! flock -n 9; then
    echo 'Another deployment is still running.' >&2
    exit 75
fi

previous_path=''

if [[ -L "$current_path" ]]; then
    previous_path="$(readlink -f "$current_path")"
fi

restore_previous() {
    exit_code=$?

    if [[ -n "$previous_path" && -d "$previous_path" ]]; then
        ln -s "$previous_path" "${base_path}/current.restore"
        mv -Tf "${base_path}/current.restore" "$current_path"
        (cd "$current_path" && "$php_binary" artisan queue:restart) || true
    fi

    exit "$exit_code"
}

trap restore_previous ERR

rm -f -- "${base_path}/current.rollback"
ln -s "$target_path" "${base_path}/current.rollback"
mv -Tf "${base_path}/current.rollback" "$current_path"

(
    cd "$current_path"
    "$php_binary" artisan queue:restart
)

"$health_check_script" "$current_path" "$health_url"
trap - ERR

echo "Rollback ${environment_name} succeeded: ${release_name}"
