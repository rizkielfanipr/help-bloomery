#!/usr/bin/env bash

set -Eeuo pipefail

readonly repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
readonly release_workflow="${repository_root}/.github/workflows/release.yml"
readonly legacy_workflow="${repository_root}/.github/workflows/deploy.yml"

if [[ -e "$legacy_workflow" ]]; then
    echo 'Legacy deployment workflow must remain retired after CI/CD Phase 5.' >&2
    exit 1
fi

if grep 'uses:' "$release_workflow" | grep -Ev '@[0-9a-f]{40}([[:space:]]|$)' >/dev/null; then
    echo "Workflow contains an action that is not pinned to a full commit SHA: ${release_workflow}" >&2
    exit 1
fi

grep -q '^  push:$' "$release_workflow"
grep -q 'Production approval rehearsal (no deploy)' "$release_workflow"
grep -q 'environment: production' "$release_workflow"
grep -q 'PRODUCTION_APPROVAL_REHEARSAL_ENABLED' "$release_workflow"
grep -q 'Deploy production atomically' "$release_workflow"
grep -q "vars.ATOMIC_PRODUCTION_ENABLED == 'true'" "$release_workflow"
grep -q 'release production' "$release_workflow"
grep -q 'Source: staging-verified immutable artifact' "$release_workflow"

echo 'Workflow contract checks passed.'
