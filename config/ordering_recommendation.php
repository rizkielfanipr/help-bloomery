<?php

$list = static fn (?string $value): ?array => $value === null || trim($value) === ''
    ? null
    : array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $item): bool => $item !== ''));

/*
|--------------------------------------------------------------------------
| Analytics → Ordering Recommendation (docs/ordering-recommendation-prd.md)
|--------------------------------------------------------------------------
|
| Every value that shapes a recommendation lives here and is copied into each forecast run's
| configuration snapshot, so a historical run can always be explained and reproduced.
|
| Phase 0 decisions that are not confirmed yet stay `null` on purpose:
| - `eligible_categories` null: no orderable allowlist yet → every product is analysed, flagged
|   `category_unconfirmed`, and its confidence is capped at Low;
| - `stock_location_scope` is a legacy rollout fallback. Each active Branch–Company–ESB Branch
|   mapping can override it from Ordering Recommendation Settings. An unconfigured mapping with
|   no fallback keeps stock unavailable and the recommendation Blocked;
| - `goods_in_transaction_types` empty: Goods Receipt/Delivery direction for a Store is not
|   proven, so goods-in stays null.
| Confirmed inbound has no source yet and is always stored as null (never 0).
|
*/

return [
    'algorithm_version' => 'v1',

    // Both sources are read in the same ESB unit so a product's quantities share one UOM.
    'flag_unit' => env('ORDERING_FLAG_UNIT', 'stockUnit'),

    'history_weeks' => 8,
    'horizons' => [7, 14],
    'default_horizon' => 7,

    // Weekday baseline: weight of an observation N weeks back = decay^(N-1).
    'recency_decay' => 0.85,
    'min_weekday_observations' => 2,

    // Trend: recent window vs the window right before it.
    'trend_recent_days' => 14,
    'trend_comparison_days' => 14,
    'trend_cap' => ['lower' => 0.5, 'upper' => 2.0],
    'trend_stable_band' => 0.05,

    'waste_rate_cap' => 0.30,
    'high_waste_rate' => 0.10,

    'safety_buffer_percent' => 0.10,

    'stock_stale_hours' => 48,

    // Confidence thresholds (versioned with algorithm_version).
    'confidence' => [
        'high_min_valid_days' => 42,
        'medium_min_valid_days' => 21,
        'high_max_wape' => 0.30,
        'medium_max_wape' => 0.60,
    ],

    // Priority score weights (explained next to every score).
    'priority_weights' => ['stockout' => 0.5, 'need' => 0.3, 'trend' => 0.2],

    'backtest' => [
        'holdout_days' => 14,
        'min_samples' => 14,
    ],

    'eligible_categories' => $list(env('ORDERING_ELIGIBLE_CATEGORIES')),

    // Legacy fallback only. Per-mapping web settings take precedence.
    // null = not agreed; 'all' = latest balance of every location summed; otherwise a list of names.
    'stock_location_scope' => env('ORDERING_STOCK_LOCATION_SCOPE') === 'all'
        ? 'all'
        : $list(env('ORDERING_STOCK_LOCATIONS')),

    'waste_transaction_types' => $list(env('ORDERING_WASTE_TYPES', 'Waste')) ?? [],
    'waste_item_journal_labels' => $list(env('ORDERING_WASTE_ITEM_JOURNAL_LABELS', 'Waste,Process Waste')) ?? [],
    'pos_sales_transaction_types' => $list(env('ORDERING_POS_SALES_TYPES', 'POS Sales')) ?? [],
    'goods_in_transaction_types' => $list(env('ORDERING_GOODS_IN_TYPES')) ?? [],

    // Daily sync for the previous day; run after the data is final.
    'sync_time' => env('ORDERING_SYNC_TIME', '04:00'),
    'queue' => env('ORDERING_QUEUE'),

    'ranking_limit' => 10,
    'chart_limit' => 10,
];
