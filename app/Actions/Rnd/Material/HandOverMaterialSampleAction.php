<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialSampleCycleStatus;
use App\Enums\MaterialSourcingStatus;
use App\Models\RndMaterialSampleAttachment;
use App\Models\RndMaterialSampleCycle;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use App\Services\Rnd\Material\MaterialSampleEvidenceStore;
use App\Services\Rnd\Material\MaterialWorkflowNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Purchasing hands a sample over to R&D (§16.2): a valid supplier candidate of this material,
 * quantity > 0, UOM, and at least one hand-over photo. Actor and time are set by the server. The
 * open (prepared) cycle after a resample is filled; otherwise the next cycle number is opened.
 */
class HandOverMaterialSampleAction
{
    private const ALLOWED_FROM = [
        MaterialSourcingStatus::WaitingPurchasing,
        MaterialSourcingStatus::SourcingInProgress,
        MaterialSourcingStatus::ResampleRequested,
        MaterialSourcingStatus::RejectedByRnd,
    ];

    public function __construct(
        private readonly MaterialSampleEvidenceStore $evidence,
        private readonly MaterialAuditLogger $audit,
        private readonly MaterialWorkflowNotifier $notifier,
    ) {}

    /**
     * @param  array{material_sourcing_id: int, quantity: float|string, uom_name: string, notes?: ?string, photos: array<int, UploadedFile>}  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, array $data): RndMaterialSampleCycle
    {
        if (! Gate::forUser($actor)->allows('handOverSample', $material)) {
            throw new AuthorizationException('Anda tidak berhak menyerahkan sampel.');
        }

        $validated = Validator::make($data, [
            'material_sourcing_id' => ['required', 'integer', Rule::exists('material_sourcings', 'id')->where('rnd_product_esb_material_id', $material->id)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'uom_name' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['material_sourcing_id.exists' => 'Pilih kandidat supplier dari bahan ini.'])->validate();

        $stored = $this->evidence->store($data['photos'] ?? [], $material->id, RndMaterialSampleAttachment::KIND_HANDOVER, 'photos');

        try {
            return DB::transaction(function () use ($material, $actor, $validated, $stored): RndMaterialSampleCycle {
                $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
                if (! $material->isInWorkflow() || ! $material->requiresSourcing() || ! $material->isVerifiedSynced() || ! in_array($material->sourcing_status, self::ALLOWED_FROM, true)) {
                    throw ValidationException::withMessages(['material_sourcing_id' => 'Sampel hanya dapat diserahkan untuk Sourcing Request aktif yang belum dievaluasi.']);
                }

                $cycle = RndMaterialSampleCycle::query()->where('rnd_product_esb_material_id', $material->id)
                    ->where('status', MaterialSampleCycleStatus::Prepared->value)->latest('cycle_number')->first()
                    ?? new RndMaterialSampleCycle([
                        'rnd_product_esb_material_id' => $material->id,
                        'cycle_number' => (int) RndMaterialSampleCycle::query()->where('rnd_product_esb_material_id', $material->id)->max('cycle_number') + 1,
                    ]);
                if (RndMaterialSampleCycle::query()->where('rnd_product_esb_material_id', $material->id)
                    ->whereIn('status', [MaterialSampleCycleStatus::HandedOver->value, MaterialSampleCycleStatus::Received->value])->exists()) {
                    throw ValidationException::withMessages(['material_sourcing_id' => 'Masih ada sampel yang belum selesai dievaluasi.']);
                }

                $cycle->fill([
                    'material_sourcing_id' => $validated['material_sourcing_id'],
                    'status' => MaterialSampleCycleStatus::HandedOver,
                    'quantity' => $validated['quantity'],
                    'uom_name' => $validated['uom_name'],
                    'handover_notes' => $validated['notes'] ?? null,
                    'handed_over_by' => $actor->id,
                    'handed_over_at' => now(),
                ])->save();
                $this->evidence->attach($cycle, $stored, RndMaterialSampleAttachment::KIND_HANDOVER, $actor);

                $previous = $material->sourcing_status;
                $material->forceFill(['sourcing_status' => MaterialSourcingStatus::SampleHandedOver])->save();
                $this->audit->event($material, 'sample_handed_over', $actor, from: $previous->value, to: MaterialSourcingStatus::SampleHandedOver->value, metadata: ['cycle' => $cycle->cycle_number, 'material_sourcing_id' => $cycle->material_sourcing_id]);
                $this->notifier->notify($material, 'sample_handed_over', 'Sampel diserahkan: '.$material->product_code, "Siklus sampel #{$cycle->cycle_number} diserahkan Purchasing dan menunggu konfirmasi penerimaan R&D.", includeCreator: true);

                return $cycle;
            });
        } catch (Throwable $exception) {
            $this->evidence->discard($stored);

            throw $exception;
        }
    }
}
