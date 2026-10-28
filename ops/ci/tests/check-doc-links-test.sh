#!/usr/bin/env bash

set -Eeuo pipefail

readonly checker="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/check-doc-links.sh"

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

# Builds a fresh fixture repository whose links are all valid.
make_fixture() {
    local root="$1"

    mkdir -p "${root}/docs/products/kitchen" "${root}/docs/templates/feature" "${root}/app" "${root}/ops/ci"
    cat > "${root}/docs/README.md" <<'EOF'
# Portal

- [Kitchen](products/kitchen/prd.md#2-scope)
- Example only: `[PRD](../missing/prd.md)`

Path lama: `docs/kitchen-old-prd.md`.
EOF
    cat > "${root}/docs/products/kitchen/prd.md" <<'EOF'
# PRD

## 2. Scope

Back to [portal](../../README.md).
EOF
    cat > "${root}/docs/templates/feature/README.md" <<'EOF'
[PRD](prd.md)
EOF
    cat > "${root}/app/Example.php" <<'EOF'
<?php
// docs/products/kitchen/prd.md §2 and docs/external-package.md
EOF
    printf 'docs/external-package.md\n' > "${root}/ops/ci/doc-links-allowlist.txt"
    printf '# Repo\n\nSee [docs](docs/README.md).\n' > "${root}/README.md"
}

valid_root="${temporary_directory}/valid"
make_fixture "$valid_root"
bash "$checker" "$valid_root" >/dev/null

broken_link_root="${temporary_directory}/broken-link"
make_fixture "$broken_link_root"
printf '\n[Gone](products/kitchen/missing.md)\n' >> "${broken_link_root}/docs/README.md"
expect_failure bash "$checker" "$broken_link_root"

broken_anchor_root="${temporary_directory}/broken-anchor"
make_fixture "$broken_anchor_root"
printf '\n[Gone](products/kitchen/prd.md#9-missing)\n' >> "${broken_anchor_root}/docs/README.md"
expect_failure bash "$checker" "$broken_anchor_root"

stale_reference_root="${temporary_directory}/stale-reference"
make_fixture "$stale_reference_root"
printf '// docs/kitchen-production-schedule-prd.md\n' >> "${stale_reference_root}/app/Example.php"
expect_failure bash "$checker" "$stale_reference_root"

echo 'Documentation link checker tests passed.'
