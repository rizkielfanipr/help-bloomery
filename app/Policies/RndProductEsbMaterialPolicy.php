<?php

namespace App\Policies;

use App\Models\RndProductEsbMaterial;
use App\Models\User;

/**
 * Master Bahan workflow abilities (docs/products/rnd/material-sourcing-lifecycle/prd.md §20). Every Action
 * checks these server-side; button visibility is only a convenience.
 */
class RndProductEsbMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['view rnd projects', 'view material sourcings', 'review rnd materials as it', 'view material pricelist']);
    }

    public function view(User $user, RndProductEsbMaterial $material): bool
    {
        return $this->viewAny($user);
    }

    public function edit(User $user, RndProductEsbMaterial $material): bool
    {
        return $user->can('edit rnd projects');
    }

    public function submitForReview(User $user, RndProductEsbMaterial $material): bool
    {
        return $user->can('edit rnd projects') && $user->can('submit rnd material review');
    }

    public function reviewAsIt(User $user, ?RndProductEsbMaterial $material = null): bool
    {
        return $user->can('review rnd materials as it');
    }

    public function amendTechnical(User $user, RndProductEsbMaterial $material): bool
    {
        return $this->reviewAsIt($user) && $user->can('edit rnd material technical data');
    }

    public function approveEsb(User $user, RndProductEsbMaterial $material): bool
    {
        return $this->reviewAsIt($user) && $user->can('approve rnd material esb push');
    }

    public function reconcileEsb(User $user, RndProductEsbMaterial $material): bool
    {
        return $user->can('reconcile rnd material esb');
    }

    public function handOverSample(User $user, RndProductEsbMaterial $material): bool
    {
        return $user->can('hand over material samples');
    }

    public function receiveSample(User $user, RndProductEsbMaterial $material): bool
    {
        return $user->can('receive material samples');
    }

    public function evaluateSample(User $user, RndProductEsbMaterial $material): bool
    {
        return $user->can('review material samples as rnd');
    }

    public function reopenSampleApproval(User $user, RndProductEsbMaterial $material): bool
    {
        return $user->can('reopen material sample approval');
    }

    public function viewSampleEvidence(User $user, RndProductEsbMaterial $material): bool
    {
        return $user->canAny(['view material sourcings', 'hand over material samples', 'receive material samples', 'review material samples as rnd', 'review rnd materials as it']);
    }

    public function viewAudit(User $user, RndProductEsbMaterial $material): bool
    {
        return $user->canAny(['view material workflow audit', 'review rnd materials as it']);
    }

    public function retryNotification(User $user, RndProductEsbMaterial $material): bool
    {
        return $user->can('retry material notifications');
    }
}
