<?php

namespace App\Actions\Purchasing\NonPoReceipt;

use App\Actions\Inventory\Receiving\TransitionNonPoReceiptAction;
use App\Enums\NonPoReceiptStatus;
use App\Models\NonPoReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StartNonPoReceiptProcessingAction
{
    public function __construct(private readonly TransitionNonPoReceiptAction $transition) {}

    public function execute(User $actor, NonPoReceipt $receipt): NonPoReceipt
    {
        return DB::transaction(function () use ($actor, $receipt): NonPoReceipt {
            $locked = NonPoReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('startProcessing', $locked);

            return $this->transition->execute($locked, NonPoReceiptStatus::PoInProgress, $actor, null, [
                'processed_by' => $actor->id, 'processed_at' => now(),
            ]);
        });
    }
}
