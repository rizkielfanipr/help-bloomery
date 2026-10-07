<?php

namespace App\Policies;

use App\Models\RndProjectTaskTemplate;
use App\Models\User;

/**
 * Template management (docs/rnd-project-checkpoint-calendar-prd.md §8.2). Managing a template does
 * not grant creating Tasks — applying one is authorized separately by
 * `RndProjectTaskPolicy::applyTemplate()`.
 */
class RndProjectTaskTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view rnd project task templates') || $user->can('manage rnd project task templates');
    }

    public function view(User $user, RndProjectTaskTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage rnd project task templates');
    }

    public function update(User $user, RndProjectTaskTemplate $template): bool
    {
        return $user->can('manage rnd project task templates');
    }

    /**
     * A template that was ever applied keeps its audit trail and is deactivated instead (§12.9).
     */
    public function delete(User $user, RndProjectTaskTemplate $template): bool
    {
        return $user->can('manage rnd project task templates') && ! $template->hasBeenApplied();
    }
}
