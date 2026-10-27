#!/usr/bin/env bash

set -Eeuo pipefail
umask 002

readonly original_command="${SSH_ORIGINAL_COMMAND:-}"
readonly deployment_root="${DEPLOY_ROOT:-/var/www/bloomery}"
readonly release_script="${DEPLOY_RELEASE_SCRIPT:-/home/deploy/bin/bloomery-release/deploy-release.sh}"

if [[ ! "$original_command" =~ ^release[[:space:]]+(staging|production)[[:space:]]+([1-9][0-9]*)[[:space:]]+([0-9a-f]{40})[[:space:]]+([0-9a-f]{64})$ ]]; then
    echo 'Release command was rejected.' >&2
    exit 64
fi

readonly environment_name="${BASH_REMATCH[1]}"
readonly run_number="${BASH_REMATCH[2]}"
readonly commit_sha="${BASH_REMATCH[3]}"
readonly expected_checksum="${BASH_REMATCH[4]}"
readonly incoming_path="${deployment_root}/${environment_name}/incoming"
readonly archive_name="help-bloomery-${commit_sha}.tar.gz"
readonly archive_path="${incoming_path}/${archive_name}"
readonly checksum_path="${archive_path}.sha256"
readonly health_url_path="${deployment_root}/${environment_name}/shared/health-url"
readonly production_gate_path="${deployment_root}/production/shared/atomic-release-enabled"

if [[ ! -d "$incoming_path" ]]; then
    echo 'Incoming release directory does not exist.' >&2
    exit 66
fi

if [[ "$environment_name" == 'production' && ! -f "$production_gate_path" ]]; then
    echo 'Atomic production releases are not enabled on this server.' >&2
    exit 69
fi

temporary_archive="$(mktemp "${incoming_path}/.${archive_name}.XXXXXX")"

cleanup() {
    rm -f -- "$temporary_archive"
}

trap cleanup EXIT

cat > "$temporary_archive"

actual_checksum="$(sha256sum "$temporary_archive" | awk '{print $1}')"

if [[ "$actual_checksum" != "$expected_checksum" ]]; then
    echo 'Uploaded release checksum does not match.' >&2
    exit 65
fi

mv -f -- "$temporary_archive" "$archive_path"
printf '%s  %s\n' "$expected_checksum" "$archive_name" > "$checksum_path"
trap - EXIT

if [[ ! -f "$health_url_path" ]]; then
    echo 'Environment health URL file does not exist.' >&2
    exit 66
fi

APP_HEALTH_URL="$(tr -d '\r\n' < "$health_url_path")"
export APP_HEALTH_URL

exec "$release_script" \
    "$environment_name" \
    "$run_number" \
    "$commit_sha" \
    "$archive_path" \
    "$checksum_path"
