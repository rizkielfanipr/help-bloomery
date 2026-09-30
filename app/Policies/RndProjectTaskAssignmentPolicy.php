<?php

namespace App\Policies;

use App\Models\RndProjectTaskAssignment;
use App\Models\User;

/**
 * Permission strings match docs/rnd-project-task-calendar-prd.md §7. `respond()` enforces
 * ownership (business rule #10: a user may never respond to another user's assignment) even for
 * users who otherwise hold the `respond rnd project tasks` permission.
 */
class RndProjectTaskAssignmentPolicy
{
    public function view(User $user, RndProjectTaskAssignment $assignment): bool
    {
        return app(RndProjectTaskPolicy::class)->view($user, $assignment->task);
    }

    public function respond(User $user, RndProjectTaskAssignment $assignment): bool
    {
        return $user->can('respond rnd project tasks') && $assignment->user_id === $user->id;
    }

    public function review(User $user, RndProjectTaskAssignment $assignment): bool
    {
        return $user->can('review rnd project task follow ups');
    }
}
