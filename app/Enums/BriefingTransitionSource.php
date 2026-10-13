<?php

namespace App\Enums;

/**
 * docs/daily-briefing-bundle-approval-prd.md §10.3: where a recorded bundle status change came from.
 */
enum BriefingTransitionSource: string
{
    case UserAction = 'user_action';
    case Migration = 'migration';
    case SystemReconciliation = 'system_reconciliation';
}
