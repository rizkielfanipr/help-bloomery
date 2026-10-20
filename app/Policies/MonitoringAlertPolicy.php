<?php

namespace App\Policies;

use App\Models\MonitoringAlert;
use App\Models\User;

class MonitoringAlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view system monitoring');
    }

    public function acknowledge(User $user, MonitoringAlert $alert): bool
    {
        return $this->viewAny($user) && $user->can('acknowledge monitoring alerts') && $alert->acknowledged_at === null;
    }
}
