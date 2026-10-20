<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
|--------------------------------------------------------------------------
| System Monitoring (docs/system-monitoring-prd.md)
|--------------------------------------------------------------------------
|
| Every value below is a conservative default. Thresholds, intervals, alert channels, maintenance
| windows, and retention can be tightened from the Monitoring Settings page within the validated
| ranges declared in `limits`. Secrets (deployment ingestion secret) are only read from the
| environment and are never editable or displayed from the UI.
|
*/

return [
    'environment' => env('MONITORING_ENVIRONMENT', env('APP_ENV', 'production')),

    /*
    | Commit currently deployed. When empty, the newest succeeded deployment recorded through the
    | ingestion contract for this environment is used; otherwise it stays unknown.
    */
    'deploy_commit' => env('MONITORING_DEPLOY_COMMIT'),

    'esb_transactions' => [
        'enabled' => (bool) env('MONITORING_ESB_TRANSACTIONS_ENABLED', true),
    ],

    'errors' => [
        'enabled' => (bool) env('MONITORING_ERRORS_ENABLED', true),
        // Browser collection is a later subphase; enable only after backend monitoring is stable.
        'browser_enabled' => (bool) env('MONITORING_BROWSER_ERRORS_ENABLED', false),
        'throttle_seconds' => 60,
        'ignore' => [
            ValidationException::class,
            AuthenticationException::class,
            AuthorizationException::class,
            TokenMismatchException::class,
            NotFoundHttpException::class,
        ],
    ],

    'queue' => [
        'heartbeat_queue' => env('MONITORING_HEARTBEAT_QUEUE'),
    ],

    'heartbeats' => [
        'scheduler_late_seconds' => 180,
        'queue_late_seconds' => 300,
    ],

    /*
    | Service checks. `interval_seconds`, `timeout_seconds`, thresholds and enablement are copied
    | to `monitoring_services` on first run and are then editable from Settings.
    */
    'checks' => [
        'application' => ['name' => 'Application', 'group' => 'core', 'critical' => true, 'interval_seconds' => 60, 'timeout_seconds' => 5, 'failure_threshold' => 2, 'recovery_threshold' => 1],
        'database' => ['name' => 'Database', 'group' => 'core', 'critical' => true, 'interval_seconds' => 60, 'timeout_seconds' => 5, 'failure_threshold' => 2, 'recovery_threshold' => 1],
        'cache' => ['name' => 'Cache', 'group' => 'core', 'critical' => false, 'interval_seconds' => 120, 'timeout_seconds' => 5, 'failure_threshold' => 2, 'recovery_threshold' => 1],
        'queue' => ['name' => 'Queue Worker', 'group' => 'core', 'critical' => true, 'interval_seconds' => 60, 'timeout_seconds' => 5, 'failure_threshold' => 3, 'recovery_threshold' => 1],
        'scheduler' => ['name' => 'Scheduler', 'group' => 'core', 'critical' => true, 'interval_seconds' => 60, 'timeout_seconds' => 5, 'failure_threshold' => 2, 'recovery_threshold' => 1],
        'object_storage' => ['name' => 'Object Storage', 'group' => 'core', 'critical' => false, 'interval_seconds' => 900, 'timeout_seconds' => 10, 'failure_threshold' => 2, 'recovery_threshold' => 1, 'disk' => env('MONITORING_STORAGE_DISK', 'b2')],
        'disk_space' => ['name' => 'Disk Server', 'group' => 'capacity', 'critical' => false, 'interval_seconds' => 300, 'timeout_seconds' => 5, 'failure_threshold' => 1, 'recovery_threshold' => 1, 'warning_percent' => 75, 'critical_percent' => 90],
        'esb_core' => ['name' => 'ESB Auth & Master Product', 'group' => 'esb', 'critical' => true, 'interval_seconds' => 300, 'timeout_seconds' => 10, 'failure_threshold' => 2, 'recovery_threshold' => 2],
        'esb_bom' => ['name' => 'BOM ESB', 'group' => 'esb', 'critical' => false, 'interval_seconds' => 300, 'timeout_seconds' => 10, 'failure_threshold' => 2, 'recovery_threshold' => 2],
        'esb_company_core' => ['name' => 'Item Journal & Goods Receipt ESB', 'group' => 'esb', 'critical' => false, 'interval_seconds' => 300, 'timeout_seconds' => 10, 'failure_threshold' => 2, 'recovery_threshold' => 2, 'company_code' => env('MONITORING_ESB_COMPANY_CODE', 'BLSS')],
        'esb_promotion' => ['name' => 'Promotion ESB', 'group' => 'esb', 'critical' => false, 'interval_seconds' => 300, 'timeout_seconds' => 10, 'failure_threshold' => 2, 'recovery_threshold' => 2, 'company_code' => env('MONITORING_ESB_COMPANY_CODE', 'BLSS')],
        'ssl_certificate' => ['name' => 'SSL Certificate', 'group' => 'capacity', 'critical' => false, 'enabled' => (bool) env('MONITORING_SSL_CHECK_ENABLED', false), 'interval_seconds' => 43200, 'timeout_seconds' => 10, 'failure_threshold' => 1, 'recovery_threshold' => 1, 'warning_days' => 30, 'critical_days' => 14],
    ],

    'alerts' => [
        'in_app' => true,
        'mail' => (bool) env('MONITORING_ALERT_MAIL_ENABLED', false),
        'mail_recipients' => array_values(array_filter(explode(',', (string) env('MONITORING_ALERT_MAIL_RECIPIENTS', '')))),
        // WhatsApp has no sending provider in this repository (only wa.me CTA links), so it stays
        // "Not Configured" until a provider is approved.
        'whatsapp' => false,
        'reminder_minutes' => 60,
        'auto_incident' => false,
        // New/regressed error fingerprints only alert when enabled: many ESB rejections are
        // handled and reported by domain code and do not need action.
        'errors' => (bool) env('MONITORING_ERROR_ALERTS_ENABLED', false),
        'acknowledge_sla_minutes' => 30,
        'recovery_sla_minutes' => 240,
    ],

    'deployments' => [
        'ingestion_secret' => env('MONITORING_DEPLOYMENT_SECRET'),
        'timestamp_tolerance_seconds' => 300,
        'long_running_minutes' => 20,
    ],

    'retention' => [
        'check_results_days' => 30,
        'transactions_days' => 180,
        'payload_days' => 60,
        'errors_days' => 90,
        'deployments_days' => 730,
        'aggregates_days' => 730,
        'scheduled_task_runs_days' => 30,
    ],

    /*
    | Validated ranges for values editable from the Settings page.
    */
    'limits' => [
        'interval_seconds' => [60, 86400],
        'timeout_seconds' => [1, 30],
        'threshold' => [1, 10],
        'reminder_minutes' => [5, 1440],
        'heartbeat_late_seconds' => [60, 3600],
        'retention' => [
            'check_results_days' => [7, 90],
            'transactions_days' => [30, 730],
            'payload_days' => [7, 90],
            'errors_days' => [30, 365],
            'deployments_days' => [180, 1095],
        ],
    ],

    'overview_cache_seconds' => 30,
];
