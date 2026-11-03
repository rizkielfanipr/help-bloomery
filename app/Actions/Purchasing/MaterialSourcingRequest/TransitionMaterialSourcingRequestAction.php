<?php

namespace App\Actions\Purchasing\MaterialSourcingRequest;

use App\Enums\MaterialSourcingRequestStatus;
use App\Models\MaterialSourcingRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The one place a Request Sourcing status changes after submission: it enforces the lifecycle of
 * PRD §13 and appends the history row. Callers hold a transaction and a row lock on the request.
 */
class TransitionMaterialSourcingRequestAction
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    public function execute(MaterialSourcingRequest $request, MaterialSourcingRequestStatus $to, ?User $actor, ?string $reason = null, array $attributes = [], array $metadata = []): MaterialSourcingRequest
    {
        $from = $request->status;

        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages(['status' => "Status {$from->getLabel()} tidak dapat berubah menjadi {$to->getLabel()}."]);
        }

        $request->forceFill(['status' => $to] + $attributes + ($to->isClosed() ? ['closed_at' => now()] : []))->save();
        $request->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
        ]);

        return $request;
    }
}
