<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorComplianceIncident;

class VendorComplianceIncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view vendor compliance incidents');
    }

    public function view(User $user, VendorComplianceIncident $incident): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, VendorComplianceIncident $incident): bool
    {
        return $this->view($user, $incident)
            && $user->can('edit vendor compliance incidents');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, VendorComplianceIncident $incident): bool
    {
        return false;
    }
}
