#!/usr/bin/env bash

set -Eeuo pipefail

readonly checker="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/check-code-map.sh"

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

bash -n "$checker"

# Builds a fixture repository with one module whose code map matches existing files.
make_fixture() {
    local root="$1"

    mkdir -p "${root}/docs/products/kitchen" "${root}/app/Actions/Kitchen" "${root}/app/Models" "${root}/tests/Feature"
    touch "${root}/app/Models/KitchenSchedule.php" "${root}/app/Models/KitchenItem.php" \
        "${root}/tests/Feature/KitchenPageTest.php" "${root}/app/Actions/Kitchen/CancelAction.php"
    cat > "${root}/docs/products/kitchen/README.md" <<'EOF'
# Kitchen

## Peta kode

| Lapisan | Lokasi |
|---|---|
| Logika | `app/Actions/Kitchen/` |
| Model | `app/Models/Kitchen{Schedule,Item}.php` |
| Test | `tests/Feature/Kitchen*Test.php` |

## Data

Not a code map: `app/DoesNotExist.php`.
EOF
}

valid_root="${temporary_directory}/valid"
make_fixture "$valid_root"
bash "$checker" "$valid_root" >/dev/null

missing_file_root="${temporary_directory}/missing-file"
make_fixture "$missing_file_root"
rm "${missing_file_root}/tests/Feature/KitchenPageTest.php"
expect_failure bash "$checker" "$missing_file_root"

missing_alternative_root="${temporary_directory}/missing-alternative"
make_fixture "$missing_alternative_root"
rm "${missing_alternative_root}/app/Models/KitchenItem.php"
expect_failure bash "$checker" "$missing_alternative_root"

directory_expected_root="${temporary_directory}/directory-expected"
make_fixture "$directory_expected_root"
rm -r "${directory_expected_root}/app/Actions/Kitchen"
touch "${directory_expected_root}/app/Actions/Kitchen"
expect_failure bash "$checker" "$directory_expected_root"

missing_section_root="${temporary_directory}/missing-section"
make_fixture "$missing_section_root"
printf '# Kitchen\n\nNo map.\n' > "${missing_section_root}/docs/products/kitchen/README.md"
expect_failure bash "$checker" "$missing_section_root"

echo 'Code map checker tests passed.'
