<?php

namespace App\Policies;

use App\Models\MonitoringError;
use App\Models\User;

/**
 * Error Monitor access (docs/products/system-monitoring/prd.md §13). Stack frames are technical details.
 */
class MonitoringErrorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view system monitoring') && $user->can('view monitoring technical details');
    }

    public function view(User $user, MonitoringError $error): bool
    {
        return $this->viewAny($user);
    }

    public function triage(User $user, MonitoringError $error): bool
    {
        return $this->viewAny($user) && $user->can('acknowledge monitoring alerts');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MonitoringError $error): bool
    {
        return false;
    }

    public function delete(User $user, MonitoringError $error): bool
    {
        return false;
    }
}
