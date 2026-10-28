<?php

namespace App\Policies;

use App\Enums\MonitoringTransactionStatus;
use App\Models\MonitoringTransaction;
use App\Models\User;

/**
 * ESB Transaction Monitor access (docs/products/system-monitoring/prd.md §7, §20). Payload summaries need
 * their own permission; Management never receives them.
 */
class MonitoringTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view system monitoring') && $user->can('view monitoring technical details');
    }

    public function view(User $user, MonitoringTransaction $transaction): bool
    {
        return $this->viewAny($user)
            && ($user->canAccessAllBranches() || $user->canAccessBranch($transaction->branch_id));
    }

    public function viewPayload(User $user, MonitoringTransaction $transaction): bool
    {
        return $this->view($user, $transaction) && $user->can('view monitoring sanitized payloads');
    }

    public function reconcile(User $user, MonitoringTransaction $transaction): bool
    {
        return $this->view($user, $transaction)
            && $user->can('reconcile esb transactions')
            && $transaction->isParent()
            && in_array($transaction->status, [MonitoringTransactionStatus::Unknown, MonitoringTransactionStatus::Partial, MonitoringTransactionStatus::Failed], true);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MonitoringTransaction $transaction): bool
    {
        return false;
    }

    public function delete(User $user, MonitoringTransaction $transaction): bool
    {
        return false;
    }
}
