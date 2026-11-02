<?php

namespace App\Actions\Inventory\Receiving;

use App\Enums\NonPoReceiptStatus;
use App\Models\NonPoReceipt;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The one place a PO Susulan status changes: it enforces the lifecycle and writes the audit row.
 * Callers hold a transaction and a row lock on the receipt.
 */
class TransitionNonPoReceiptAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(NonPoReceipt $receipt, NonPoReceiptStatus $to, ?User $actor, ?string $notes = null, array $attributes = []): NonPoReceipt
    {
        $from = $receipt->status;

        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages(['status' => "Status {$from->getLabel()} tidak dapat berubah menjadi {$to->getLabel()}."]);
        }

        $receipt->update(['status' => $to] + $attributes);
        $receipt->statusHistories()->create(['from_status' => $from, 'to_status' => $to, 'actor_id' => $actor?->id, 'notes' => $notes]);

        return $receipt;
    }
}
