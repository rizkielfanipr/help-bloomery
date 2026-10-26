#!/usr/bin/env bash

set -Eeuo pipefail

readonly repository_root="$(git rev-parse --show-toplevel)"
readonly commit_sha="${GITHUB_SHA:-$(git -C "$repository_root" rev-parse HEAD)}"
readonly run_id="${GITHUB_RUN_ID:-local}"
readonly run_number="${GITHUB_RUN_NUMBER:-0}"
readonly output_directory="${RELEASE_OUTPUT_DIRECTORY:-${repository_root}/dist}"
readonly archive_name="help-bloomery-${commit_sha}.tar.gz"
readonly archive_path="${output_directory}/${archive_name}"
readonly checksum_path="${archive_path}.sha256"

if [[ ! "$commit_sha" =~ ^[0-9a-f]{40}$ ]]; then
    echo 'GITHUB_SHA must be a full 40-character lowercase hexadecimal commit.' >&2
    exit 64
fi

if [[ ! "$run_number" =~ ^[0-9]+$ ]]; then
    echo 'GITHUB_RUN_NUMBER must be a non-negative integer.' >&2
    exit 64
fi

for required_path in composer.lock package-lock.json vendor public/build/manifest.json; do
    if [[ ! -e "${repository_root}/${required_path}" ]]; then
        echo "Required release input is missing: ${required_path}" >&2
        exit 66
    fi
done

temporary_directory="$(mktemp -d)"
release_root="${temporary_directory}/release"

cleanup() {
    rm -rf -- "$temporary_directory"
}

trap cleanup EXIT

mkdir -p "$release_root" "$output_directory"

git -C "$repository_root" archive "$commit_sha" | tar -x -C "$release_root"

rm -rf -- "${release_root}/public/build"
cp -a "${repository_root}/vendor" "${release_root}/vendor"
mkdir -p "${release_root}/public"
cp -a "${repository_root}/public/build" "${release_root}/public/build"

rm -rf -- \
    "${release_root}/.env" \
    "${release_root}/.git" \
    "${release_root}/node_modules" \
    "${release_root}/storage"

mkdir -p "${release_root}/bootstrap/cache"
find "${release_root}/bootstrap/cache" -mindepth 1 -maxdepth 1 -type f -delete

php -r '
$manifest = [
    "application" => "help-bloomery",
    "commit" => $argv[1],
    "run_id" => $argv[2],
    "run_number" => $argv[3],
    "built_at" => gmdate("c"),
    "php" => PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION,
    "node" => trim((string) shell_exec("node --version")),
];

file_put_contents(
    $argv[4],
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
);
' "$commit_sha" "$run_id" "$run_number" "${release_root}/.release-manifest.json"

while IFS= read -r -d '' symbolic_link; do
    resolved_target="$(realpath -m "$symbolic_link")"

    if [[ "$resolved_target" != "$release_root"/* ]]; then
        echo "Artifact contains a symbolic link outside the release root: ${symbolic_link}" >&2
        exit 65
    fi
done < <(find "$release_root" -type l -print0)

tar -czf "$archive_path" -C "$release_root" .

if tar -tzf "$archive_path" | grep -Eq '(^|/)(\.env|\.git|node_modules|storage)(/|$)'; then
    echo 'Artifact contains a forbidden runtime or secret path.' >&2
    exit 65
fi

(
    cd "$output_directory"
    sha256sum "$archive_name" > "${archive_name}.sha256"
    sha256sum --check "${archive_name}.sha256"
)

printf 'Release artifact: %s\nChecksum: %s\n' "$archive_path" "$checksum_path"
