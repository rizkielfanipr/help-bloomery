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

class ReviewLegacyMaterialSourcingAction
{
    /**
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, string $decision, ?int $candidateId, ?string $note): void
    {
        if (! $actor->can('review material sourcing as rnd')) {
            throw new AuthorizationException('Anda tidak berhak melakukan review sourcing sebagai R&D.');
        }

        $validated = Validator::make([
            'decision' => $decision,
            'candidate_id' => $candidateId,
            'note' => $note,
        ], [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'candidate_id' => [Rule::requiredIf($decision === 'approve'), 'nullable', 'integer'],
            'note' => [Rule::requiredIf($decision === 'reject'), 'nullable', 'string', 'max:2000', $decision === 'reject' ? 'min:5' : 'nullable'],
        ])->validate();

        DB::transaction(function () use ($material, $actor, $validated): void {
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
            if ($material->isInWorkflow() || $material->sourcing_status !== MaterialSourcingStatus::PendingRndReview) {
                throw ValidationException::withMessages(['decision' => 'Status sourcing sudah berubah. Muat ulang halaman sebelum memproses.']);
            }

            if ($validated['decision'] === 'approve' && ! $material->sourcings()->whereKey($validated['candidate_id'])->exists()) {
                throw ValidationException::withMessages(['candidate_id' => 'Supplier terpilih bukan milik bahan ini.']);
            }

            $approved = $validated['decision'] === 'approve';
            $material->forceFill([
                'sourcing_selected_id' => $approved ? $validated['candidate_id'] : null,
                'rnd_reviewed_by' => $actor->id,
                'rnd_reviewed_at' => now(),
                'rnd_note' => $validated['note'] ?? null,
                'sourcing_status' => $approved ? MaterialSourcingStatus::PendingFinanceReview : MaterialSourcingStatus::Rejected,
            ])->save();

            $material->sourcingApprovals()->create([
                'stage' => 'rnd',
                'action' => $approved ? 'approved' : 'rejected',
                'actor_id' => $actor->id,
                'notes' => $validated['note'] ?? null,
            ]);
        });
    }
}
