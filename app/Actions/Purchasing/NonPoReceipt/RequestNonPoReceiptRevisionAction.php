<?php

namespace App\Actions\Purchasing\NonPoReceipt;

use App\Actions\Inventory\Receiving\TransitionNonPoReceiptAction;
use App\Enums\NonPoReceiptStatus;
use App\Models\NonPoReceipt;
use App\Models\User;
use App\Services\NonPoReceiptNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RequestNonPoReceiptRevisionAction
{
    public function __construct(
        private readonly TransitionNonPoReceiptAction $transition,
        private readonly NonPoReceiptNotifier $notifier,
    ) {}

    public function execute(User $actor, NonPoReceipt $receipt, string $reason): NonPoReceipt
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan perbaikan wajib diisi.']);
        }

        $updated = DB::transaction(function () use ($actor, $receipt, $reason): NonPoReceipt {
            $locked = NonPoReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('requestRevision', $locked);

            return $this->transition->execute($locked, NonPoReceiptStatus::RevisionRequested, $actor, $reason);
        });

        $this->notifier->revisionRequested($updated, $reason);

        return $updated;
    }
}
