<?php

namespace App\Actions;

use App\Enums\ItRequestStatus;
use App\Models\ErpRepairRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClaimErpRequestWarrantyAction
{
    public function execute(ErpRepairRequest $request, User $actor, string $notes): ErpRepairRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes): ErpRepairRequest {
            $request = ErpRepairRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($request->requester_id !== $actor->getKey()) {
                throw new AuthorizationException('Warranty hanya dapat diklaim oleh pemohon tiket.');
            }

            if ($request->status !== ItRequestStatus::Warranty || ! $request->warranty_expires_at?->isFuture()) {
                throw ValidationException::withMessages(['claimNotes' => 'Masa Warranty tiket sudah berakhir.']);
            }

            $notes = trim($notes);
            if ($notes === '') {
                throw ValidationException::withMessages(['claimNotes' => 'Alasan klaim Warranty wajib diisi.']);
            }

            $claimedAt = now();
            $request->forceFill([
                'status' => ItRequestStatus::Submitted,
                'submitted_at' => $claimedAt,
                'first_responded_at' => null,
                'response_business_seconds' => null,
                'resolved_at' => null,
                'resolution_business_seconds' => null,
                'closed_by' => null,
                'warranty_claimed_at' => $claimedAt,
                'warranty_claim_notes' => $notes,
                'warranty_started_at' => null,
                'warranty_expires_at' => null,
            ])->save();
            $request->activities()->create([
                'actor_id' => $actor->getKey(),
                'action' => 'warranty_claimed',
                'from_status' => ItRequestStatus::Warranty->value,
                'to_status' => ItRequestStatus::Submitted->value,
                'notes' => $notes,
            ]);

            return $request;
        });
    }
}
