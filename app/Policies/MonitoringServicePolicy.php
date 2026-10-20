<?php

namespace App\Policies;

use App\Models\MonitoringService;
use App\Models\User;

/**
 * Service Health snapshots are readable by Management; editing check configuration needs
 * `manage monitoring settings` (docs/system-monitoring-prd.md §7).
 */
class MonitoringServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view system monitoring');
    }

    public function view(User $user, MonitoringService $service): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, MonitoringService $service): bool
    {
        return $user->can('manage monitoring settings');
    }
}
