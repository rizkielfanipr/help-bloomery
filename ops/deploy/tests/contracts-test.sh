#!/usr/bin/env bash

set -Eeuo pipefail

readonly scripts_directory="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly valid_sha='0123456789abcdef0123456789abcdef01234567'
readonly valid_checksum='0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'

temporary_directory="$(mktemp -d)"

cleanup() {
    rm -rf -- "$temporary_directory"
}

trap cleanup EXIT

expect_failure() {
    if "$@" >/dev/null 2>&1; then
        echo "Expected command to fail: $*" >&2
        exit 1
    fi
}

for script in \
    build-artifact.sh \
    deploy-release.sh \
    health-check.sh \
    receive-release.sh \
    rollback-release.sh; do
    bash -n "${scripts_directory}/${script}"
done

grep -q -- '--migration-status=ran' "${scripts_directory}/deploy-release.sh"
grep -q -- 'atomic-release-enabled' "${scripts_directory}/receive-release.sh"

if grep -q -- '--migration-status=succeeded' "${scripts_directory}/deploy-release.sh"; then
    echo 'Deployment script uses an invalid migration monitoring status.' >&2
    exit 1
fi

expect_failure "${scripts_directory}/deploy-release.sh"
expect_failure "${scripts_directory}/deploy-release.sh" invalid 1 "$valid_sha" /tmp/a /tmp/b
expect_failure "${scripts_directory}/rollback-release.sh" staging invalid

mkdir -p "${temporary_directory}/staging/incoming"
expect_failure env \
    DEPLOY_ROOT="$temporary_directory" \
    SSH_ORIGINAL_COMMAND="release staging 1 ${valid_sha} invalid" \
    "${scripts_directory}/receive-release.sh"

expect_failure env \
    DEPLOY_ROOT="$temporary_directory" \
    SSH_ORIGINAL_COMMAND="release staging 1 ${valid_sha} ${valid_checksum}" \
    "${scripts_directory}/receive-release.sh" < /dev/null

mkdir -p \
    "${temporary_directory}/production/incoming" \
    "${temporary_directory}/production/shared"
printf 'https://production.example.test/up\n' \
    > "${temporary_directory}/production/shared/health-url"

if production_gate_output="$(
    env \
        DEPLOY_ROOT="$temporary_directory" \
        SSH_ORIGINAL_COMMAND="release production 1 ${valid_sha} ${valid_checksum}" \
        "${scripts_directory}/receive-release.sh" < /dev/null 2>&1
)"; then
    echo 'Expected production release to fail while the server gate is absent.' >&2
    exit 1
fi

grep -q 'Atomic production releases are not enabled on this server.' \
    <<< "$production_gate_output"

if mv --help 2>&1 | grep -q -- ' -T'; then
    deployment_root="${temporary_directory}/deployment"
    environment_root="${deployment_root}/staging"
    fake_health_check="${temporary_directory}/health-check.sh"
    fake_artisan_log="${temporary_directory}/artisan.log"
    php_binary="$(command -v php)"

    mkdir -p \
        "${environment_root}/incoming" \
        "${environment_root}/releases" \
        "${environment_root}/shared/storage"
    printf 'APP_ENV=testing\n' > "${environment_root}/shared/.env"

    printf '%s\n' \
        '#!/usr/bin/env bash' \
        'set -Eeuo pipefail' \
        'test -f "$1/artisan"' \
        'test "$2" = "https://staging.example.test/up"' \
        > "$fake_health_check"
    chmod +x "$fake_health_check"

    create_release_archive() {
        local run_number="$1"
        local commit_sha="$2"
        local source_path="${temporary_directory}/source-${run_number}"
        local archive_name="help-bloomery-${commit_sha}.tar.gz"
        local archive_path="${environment_root}/incoming/${archive_name}"

        mkdir -p "${source_path}/bootstrap/cache" "${source_path}/public/build"
        printf '%s\n' \
            '#!/usr/bin/env php' \
            '<?php' \
            'if (getenv("FAKE_ARTISAN_LOG")) {' \
            '    file_put_contents(getenv("FAKE_ARTISAN_LOG"), implode(" ", array_slice($argv, 1)).PHP_EOL, FILE_APPEND);' \
            '}' \
            'if (($argv[1] ?? null) === "list") {' \
            '    echo "monitoring:record-deployment  Record deployment metadata\\n";' \
            '}' \
            'exit(0);' \
            > "${source_path}/artisan"
        printf '{}\n' > "${source_path}/public/build/manifest.json"
        printf '{"application":"help-bloomery","commit":"%s","run_number":"%s"}\n' \
            "$commit_sha" \
            "$run_number" \
            > "${source_path}/.release-manifest.json"
        tar -czf "$archive_path" -C "$source_path" .
        (
            cd "${environment_root}/incoming"
            sha256sum "$archive_name" > "${archive_name}.sha256"
        )
    }

    first_sha='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
    second_sha='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'

    create_release_archive 10 "$first_sha"
    env \
        APP_HEALTH_URL='https://staging.example.test/up' \
        DEPLOY_HEALTH_CHECK_SCRIPT="$fake_health_check" \
        DEPLOY_PHP_BINARY="$php_binary" \
        DEPLOY_ROOT="$deployment_root" \
        FAKE_ARTISAN_LOG="$fake_artisan_log" \
        "${scripts_directory}/deploy-release.sh" \
        staging \
        10 \
        "$first_sha" \
        "${environment_root}/incoming/help-bloomery-${first_sha}.tar.gz" \
        "${environment_root}/incoming/help-bloomery-${first_sha}.tar.gz.sha256"

    test "$(readlink -f "${environment_root}/current")" = "${environment_root}/releases/10-aaaaaaaa"

    create_release_archive 11 "$second_sha"
    env \
        APP_HEALTH_URL='https://staging.example.test/up' \
        DEPLOY_HEALTH_CHECK_SCRIPT="$fake_health_check" \
        DEPLOY_PHP_BINARY="$php_binary" \
        DEPLOY_ROOT="$deployment_root" \
        FAKE_ARTISAN_LOG="$fake_artisan_log" \
        "${scripts_directory}/deploy-release.sh" \
        staging \
        11 \
        "$second_sha" \
        "${environment_root}/incoming/help-bloomery-${second_sha}.tar.gz" \
        "${environment_root}/incoming/help-bloomery-${second_sha}.tar.gz.sha256"

    test "$(readlink -f "${environment_root}/current")" = "${environment_root}/releases/11-bbbbbbbb"
    grep -q 'monitoring:record-deployment .*--migration-status=ran' "$fake_artisan_log"

    env \
        APP_HEALTH_URL='https://staging.example.test/up' \
        DEPLOY_HEALTH_CHECK_SCRIPT="$fake_health_check" \
        DEPLOY_PHP_BINARY="$php_binary" \
        DEPLOY_ROOT="$deployment_root" \
        "${scripts_directory}/rollback-release.sh" \
        staging \
        10-aaaaaaaa

    test "$(readlink -f "${environment_root}/current")" = "${environment_root}/releases/10-aaaaaaaa"
fi

echo 'Deployment script contract checks passed.'
