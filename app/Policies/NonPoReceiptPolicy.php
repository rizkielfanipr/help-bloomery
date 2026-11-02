<?php

namespace App\Policies;

use App\Enums\NonPoReceiptStatus;
use App\Models\NonPoReceipt;
use App\Models\User;

/**
 * docs/products/inventory/non-po-receiving/prd.md §9/§16: every ability checks the permission, the
 * status, and the BLSS context; hiding a button is never the security boundary.
 */
class NonPoReceiptPolicy
{
    private const COMPANY_CODE = 'BLSS';

    public function viewAny(User $user): bool
    {
        return $user->can('view non po purchase order requests');
    }

    public function view(User $user, NonPoReceipt $receipt): bool
    {
        return $this->viewAny($user) && $receipt->company_code === self::COMPANY_CODE;
    }

    public function accessEmployeeApp(User $user): bool
    {
        return $user->can('access employee app goods receipt') && $user->can('create non po receipts');
    }

    public function create(User $user): bool
    {
        return $this->accessEmployeeApp($user);
    }

    public function update(User $user, NonPoReceipt $receipt): bool
    {
        return $this->accessEmployeeApp($user)
            && $receipt->company_code === self::COMPANY_CODE
            && $receipt->status->canBeEditedByInventory();
    }

    public function submit(User $user, NonPoReceipt $receipt): bool
    {
        return $this->update($user, $receipt) && $user->can('submit non po receipts');
    }

    public function startProcessing(User $user, NonPoReceipt $receipt): bool
    {
        return $this->canProcess($user, $receipt) && $receipt->status === NonPoReceiptStatus::AwaitingPurchasing;
    }

    public function requestRevision(User $user, NonPoReceipt $receipt): bool
    {
        return $this->canProcess($user, $receipt)
            && in_array($receipt->status, [NonPoReceiptStatus::AwaitingPurchasing, NonPoReceiptStatus::PoInProgress], true);
    }

    public function verifyPurchaseOrder(User $user, NonPoReceipt $receipt): bool
    {
        return $this->canProcess($user, $receipt)
            && in_array($receipt->status, [NonPoReceiptStatus::PoInProgress, NonPoReceiptStatus::PoLinked], true);
    }

    public function resolveGoodsReceipt(User $user, NonPoReceipt $receipt): bool
    {
        return $this->canProcess($user, $receipt) && $receipt->status->needsGoodsReceiptResolution();
    }

    public function cancel(User $user, NonPoReceipt $receipt): bool
    {
        if ($receipt->company_code !== self::COMPANY_CODE || ! $receipt->status->canTransitionTo(NonPoReceiptStatus::Cancelled)) {
            return false;
        }

        // Inventory may withdraw what it still owns; a request Purchasing has received needs the separate permission.
        return $user->can('cancel non po purchase order requests')
            || ($receipt->status->canBeEditedByInventory() && $this->accessEmployeeApp($user));
    }

    public function delete(User $user, NonPoReceipt $receipt): bool
    {
        return false;
    }

    private function canProcess(User $user, NonPoReceipt $receipt): bool
    {
        return $user->can('process non po purchase order requests') && $receipt->company_code === self::COMPANY_CODE;
    }
}
