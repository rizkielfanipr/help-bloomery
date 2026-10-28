<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialSourcingStatus;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReviewLegacyMaterialSourcingFinanceAction
{
    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, string $decision, ?string $note): void
    {
        if (! $actor->can('review material sourcing as finance')) {
            throw new AuthorizationException('Anda tidak berhak melakukan review sourcing sebagai Finance.');
        }

        $validated = Validator::make(['decision' => $decision, 'note' => $note], [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => [Rule::requiredIf($decision === 'reject'), 'nullable', 'string', 'max:2000', $decision === 'reject' ? 'min:5' : 'nullable'],
        ])->validate();

        DB::transaction(function () use ($material, $actor, $validated): void {
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
            if ($material->isInWorkflow() || $material->sourcing_status !== MaterialSourcingStatus::PendingFinanceReview) {
                throw ValidationException::withMessages(['decision' => 'Status sourcing sudah berubah. Muat ulang halaman sebelum memproses.']);
            }
            if (! $material->sourcing_selected_id || ! $material->sourcings()->whereKey($material->sourcing_selected_id)->exists()) {
                throw ValidationException::withMessages(['decision' => 'Supplier pilihan R&D tidak valid untuk bahan ini.']);
            }

            $approved = $validated['decision'] === 'approve';
            $material->forceFill([
                'finance_reviewed_by' => $actor->id,
                'finance_reviewed_at' => now(),
                'finance_note' => $validated['note'] ?? null,
                'sourcing_status' => $approved ? MaterialSourcingStatus::Approved : MaterialSourcingStatus::PendingRndReview,
            ])->save();

            $material->sourcingApprovals()->create([
                'stage' => 'finance',
                'action' => $approved ? 'approved' : 'rejected',
                'actor_id' => $actor->id,
                'notes' => $validated['note'] ?? null,
            ]);
        });
    }
}
