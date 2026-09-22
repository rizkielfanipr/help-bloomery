<?php

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view goods receipts');
    }

    public function view(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $this->viewAny($user);
    }

    public function accessEmployeeApp(User $user): bool
    {
        return $user->can('access employee app goods receipt');
    }

    public function submit(User $user): bool
    {
        return $this->accessEmployeeApp($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return false;
    }

    public function delete(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return false;
    }
}
