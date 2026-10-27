<?php

namespace App\Policies;

use App\Models\MonitoringIncident;
use App\Models\User;

/**
 * Incident Center access (docs/operations/monitoring/prd.md §16). Management can read incidents;
 * only `manage monitoring incidents` can create or change them.
 */
class MonitoringIncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view system monitoring');
    }

    public function view(User $user, MonitoringIncident $incident): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->can('manage monitoring incidents');
    }

    public function manage(User $user, MonitoringIncident $incident): bool
    {
        return $this->create($user);
    }

    public function update(User $user, MonitoringIncident $incident): bool
    {
        return $this->manage($user, $incident);
    }

    public function delete(User $user, MonitoringIncident $incident): bool
    {
        return false;
    }
}
