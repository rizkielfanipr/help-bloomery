<?php

/*
 * ESB Production Order calendar for Kitchen → Jadwal Produksi
 * (docs/products/kitchen/production-schedule/prd.md §30, §38). The calendar is always shown (K18); only the
 * scheduler has a toggle, off by default until the rollout enables it.
 */
return [
    'production_orders' => [
        // Lets the scheduler dispatch the hourly incremental sync. Manual commands ignore it.
        'sync_enabled' => (bool) env('KITCHEN_PRODUCTION_ORDER_SYNC_ENABLED', false),

        // Which mappings are synced is not configured here: every active, complete mapping of a
        // Kitchen-Brand Branch in Master Branch is synced (prd.md §28, K20).

        // Companies read with the global ESB Core credential (`ESB_CORE_USERNAME`) instead of the
        // per-company one. Verified 27 Oct 2026: the per-company BLSS account gets HTTP 403 on
        // Production Order while the global account is the BLSS company (identical Branch list).
        'global_credential_companies' => array_values(array_filter(array_map(
            fn (string $code): string => mb_strtoupper(trim($code)),
            explode(',', (string) env('KITCHEN_PRODUCTION_ORDER_GLOBAL_CREDENTIAL_COMPANIES', 'BLSS')),
        ))),

        // Optional dedicated queue; empty uses the default queue.
        'queue' => env('KITCHEN_PRODUCTION_ORDER_QUEUE'),

        'incremental_days_back' => 7,
        'incremental_days_forward' => 14,
        'window_max_days' => 31,
        'backfill_max_days' => 120,
        'page_limit' => 100,
        'max_pages' => 50,
        'stale_after_minutes' => 180,
        'stuck_run_minutes' => 120,
        // Minimum minutes between runs of one mapping before "Sinkronkan Sekarang" may queue another (K22).
        'manual_sync_cooldown_minutes' => 5,
        'run_retention_days' => 90,
    ],
];
