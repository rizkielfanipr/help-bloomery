<?php

namespace App\Policies;

use App\Models\MonitoringDeployment;
use App\Models\User;

/**
 * Deployment Monitor is read-only: no deploy, redeploy, or rollback from the UI
 * (docs/system-monitoring-prd.md §14).
 */
class MonitoringDeploymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view system monitoring') && $user->can('view monitoring technical details');
    }

    public function view(User $user, MonitoringDeployment $deployment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MonitoringDeployment $deployment): bool
    {
        return false;
    }

    public function delete(User $user, MonitoringDeployment $deployment): bool
    {
        return false;
    }
}
