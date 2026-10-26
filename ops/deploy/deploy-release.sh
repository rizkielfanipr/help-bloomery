#!/usr/bin/env bash

set -Eeuo pipefail
umask 002

if (( $# != 5 )); then
    echo 'Usage: deploy-release.sh <environment> <run-number> <full-sha> <archive> <checksum>' >&2
    exit 64
fi

readonly environment_name="$1"
readonly run_number="$2"
readonly commit_sha="$3"
readonly archive_input="$4"
readonly checksum_input="$5"
readonly deployment_root="${DEPLOY_ROOT:-/var/www/bloomery}"
readonly base_path="${deployment_root}/${environment_name}"
readonly incoming_path="${base_path}/incoming"
readonly releases_path="${base_path}/releases"
readonly shared_path="${base_path}/shared"
readonly current_path="${base_path}/current"
readonly release_name="${run_number}-${commit_sha:0:8}"
readonly release_path="${releases_path}/${release_name}"
readonly health_check_script="${DEPLOY_HEALTH_CHECK_SCRIPT:-/home/deploy/bin/bloomery-release/health-check.sh}"
readonly php_binary="${DEPLOY_PHP_BINARY:-/usr/bin/php}"
readonly health_url="${APP_HEALTH_URL:-}"

case "$environment_name" in
    staging|production) ;;
    *) echo 'Environment must be staging or production.' >&2; exit 64 ;;
esac

if [[ ! "$run_number" =~ ^[1-9][0-9]*$ ]]; then
    echo 'Run number must be a positive integer.' >&2
    exit 64
fi

if [[ ! "$commit_sha" =~ ^[0-9a-f]{40}$ ]]; then
    echo 'Commit must be a full 40-character lowercase hexadecimal SHA.' >&2
    exit 64
fi

if [[ -z "$health_url" ]]; then
    echo 'APP_HEALTH_URL is required.' >&2
    exit 64
fi

for required_path in "$incoming_path" "$releases_path" "$shared_path"; do
    if [[ ! -d "$required_path" ]]; then
        echo "Required deployment directory is missing: ${required_path}" >&2
        exit 66
    fi
done

if [[ ! -f "${shared_path}/.env" || ! -d "${shared_path}/storage" ]]; then
    echo 'Shared .env or storage is missing.' >&2
    exit 66
fi

archive_path="$(realpath -e "$archive_input")"
checksum_path="$(realpath -e "$checksum_input")"

for candidate_path in "$archive_path" "$checksum_path"; do
    if [[ "$candidate_path" != "$incoming_path"/* ]]; then
        echo 'Archive and checksum must be regular files inside the environment incoming directory.' >&2
        exit 65
    fi
done

exec 9>"${base_path}/.deploy.lock"

if ! flock -n 9; then
    echo 'Another deployment is still running.' >&2
    exit 75
fi

(
    cd "$incoming_path"
    sha256sum --check "$(basename "$checksum_path")"
)

if [[ -e "$release_path" ]]; then
    echo "Release already exists: ${release_name}" >&2
    exit 65
fi

previous_release=''
switched=false

if [[ -L "$current_path" ]]; then
    previous_release="$(readlink -f "$current_path")"
fi

rollback_on_error() {
    exit_code=$?

    if [[ "$switched" == true && -n "$previous_release" && -d "$previous_release" ]]; then
        rm -f -- "${base_path}/current.rollback"
        ln -s "$previous_release" "${base_path}/current.rollback"
        mv -Tf "${base_path}/current.rollback" "$current_path"
        (cd "$current_path" && "$php_binary" artisan queue:restart) || true
    elif [[ "$switched" == false ]]; then
        rm -rf -- "$release_path"
    fi

    echo "Deployment failed for release ${release_name}." >&2
    exit "$exit_code"
}

trap rollback_on_error ERR

if [[ -L "$current_path" && -f "${current_path}/.release-manifest.json" ]]; then
    active_run_number="$("$php_binary" -r '$manifest = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR); echo $manifest["run_number"] ?? "";' "${current_path}/.release-manifest.json")"

    if [[ "$active_run_number" =~ ^[0-9]+$ ]] && (( run_number <= active_run_number )); then
        echo "Run number ${run_number} is not newer than active run ${active_run_number}." >&2
        exit 65
    fi
fi

while IFS= read -r archive_entry; do
    normalized_entry="${archive_entry#./}"

    if [[ "$archive_entry" == /* || "$normalized_entry" == '..' || "$normalized_entry" == ../* || "$normalized_entry" == */../* ]]; then
        echo "Archive contains an unsafe path: ${archive_entry}" >&2
        exit 65
    fi

    if [[ "$normalized_entry" =~ (^|/)(\.env|\.git|node_modules|storage)(/|$) ]]; then
        echo "Archive contains a forbidden path: ${archive_entry}" >&2
        exit 65
    fi
done < <(tar -tzf "$archive_path")

mkdir -p "$release_path"
tar -xzf "$archive_path" --no-same-owner -C "$release_path"

manifest_path="${release_path}/.release-manifest.json"

"$php_binary" -r '
$manifest = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);

if (($manifest["application"] ?? null) !== "help-bloomery") {
    fwrite(STDERR, "Unexpected application in release manifest.\n");
    exit(1);
}

if (($manifest["commit"] ?? null) !== $argv[2] || (string) ($manifest["run_number"] ?? "") !== $argv[3]) {
    fwrite(STDERR, "Release manifest does not match the requested commit and run.\n");
    exit(1);
}
' "$manifest_path" "$commit_sha" "$run_number"

while IFS= read -r -d '' symbolic_link; do
    resolved_target="$(realpath -m "$symbolic_link")"

    if [[ "$resolved_target" != "$release_path"/* ]]; then
        echo "Extracted artifact contains a symbolic link outside the release: ${symbolic_link}" >&2
        exit 65
    fi
done < <(find "$release_path" -type l -print0)

rm -rf -- "${release_path}/storage"
ln -s "${shared_path}/storage" "${release_path}/storage"
ln -s "${shared_path}/.env" "${release_path}/.env"
install -d -m 2775 "${release_path}/bootstrap/cache"

(
    cd "$release_path"
    "$php_binary" artisan optimize:clear
    "$php_binary" artisan migrate --isolated --force --no-interaction
    "$php_binary" artisan permissions:sync --no-interaction
    "$php_binary" artisan optimize
)

rm -f -- "${base_path}/current.next"
ln -s "$release_path" "${base_path}/current.next"
mv -Tf "${base_path}/current.next" "$current_path"
switched=true

(
    cd "$current_path"
    "$php_binary" artisan queue:restart
)

"$health_check_script" "$current_path" "$health_url"

if (cd "$current_path" && "$php_binary" artisan list --raw | grep -q '^monitoring:record-deployment[[:space:]]'); then
    if ! (
        cd "$current_path"
        "$php_binary" artisan monitoring:record-deployment \
            --environment="$environment_name" \
            --provider=github_actions \
            --run-id="${GITHUB_RUN_ID:-$run_number}" \
            --status=succeeded \
            --stage=completed \
            --branch=main \
            --commit="$commit_sha" \
            --actor="${GITHUB_ACTOR:-deploy}" \
            --migration-status=ran \
            --health-status=healthy \
            --finished-at="$(date --iso-8601=seconds)"
    ); then
        echo 'Warning: release succeeded, but deployment monitoring could not be recorded.' >&2
    fi
fi

mapfile -t obsolete_releases < <(find "$releases_path" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -Vr | tail -n +6)

for obsolete_release in "${obsolete_releases[@]}"; do
    obsolete_path="${releases_path}/${obsolete_release}"

    if [[ "$obsolete_path" != "$previous_release" && "$obsolete_path" != "$release_path" ]]; then
        rm -rf -- "$obsolete_path"
    fi
done

rm -f -- "$archive_path" "$checksum_path"
trap - ERR

echo "Deployment ${environment_name} succeeded: ${release_name}"
