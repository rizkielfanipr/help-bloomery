#!/usr/bin/env bash

set -Eeuo pipefail

readonly shard_index="${SHARD_INDEX:-}"
readonly shard_total="${SHARD_TOTAL:-}"
readonly batch_size="${SHARD_BATCH_SIZE:-20}"

if [[ ! "$shard_index" =~ ^[1-9][0-9]*$ ]]; then
    echo 'SHARD_INDEX must be a positive integer.' >&2
    exit 64
fi

if [[ ! "$shard_total" =~ ^[1-9][0-9]*$ ]]; then
    echo 'SHARD_TOTAL must be a positive integer.' >&2
    exit 64
fi

if (( shard_index > shard_total )); then
    echo 'SHARD_INDEX must not be greater than SHARD_TOTAL.' >&2
    exit 64
fi

if [[ ! "$batch_size" =~ ^[1-9][0-9]*$ ]]; then
    echo 'SHARD_BATCH_SIZE must be a positive integer.' >&2
    exit 64
fi

if [[ ! -d tests/Feature ]]; then
    echo 'The tests/Feature directory does not exist.' >&2
    exit 66
fi

feature_tests=()

while IFS= read -r -d '' test_file; do
    feature_tests+=("$test_file")
done < <(find tests/Feature -type f -name '*.php' -print0 | sort -z)

selected_tests=()
selected_remainder=$((shard_index - 1))

for test_index in "${!feature_tests[@]}"; do
    if (( test_index % shard_total == selected_remainder )); then
        selected_tests+=("${feature_tests[$test_index]}")
    fi
done

if (( ${#selected_tests[@]} == 0 )); then
    echo "No Feature tests were assigned to shard ${shard_index}/${shard_total}." >&2
    exit 65
fi

if [[ "${SHARD_LIST_ONLY:-false}" == 'true' ]]; then
    printf '%s\n' "${selected_tests[@]}"
    exit 0
fi

printf 'Running %s Feature test files in shard %s/%s.\n' \
    "${#selected_tests[@]}" \
    "$shard_index" \
    "$shard_total"

for ((batch_start = 0; batch_start < ${#selected_tests[@]}; batch_start += batch_size)); do
    batch=("${selected_tests[@]:batch_start:batch_size}")

    printf 'Running batch starting at shard index %s (%s files).\n' \
        "$batch_start" \
        "${#batch[@]}"

    php artisan test --compact "${batch[@]}"
done
