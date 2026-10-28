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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * R&D confirms receipt with its own photos (§16.3). The Purchasing hand-over photo is never
 * accepted as receipt evidence. A second confirmation of the same cycle is refused.
 */
class ConfirmMaterialSampleReceiptAction
{
    public const CONDITIONS = ['good' => 'Baik / sesuai', 'partial' => 'Sebagian / kurang', 'damaged' => 'Rusak'];

    public function __construct(private readonly MaterialSampleEvidenceStore $evidence, private readonly MaterialAuditLogger $audit) {}

    /**
     * @param  array{received_quantity: float|string, received_condition: string, notes?: ?string, photos: array<int, UploadedFile>}  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, array $data): RndMaterialSampleCycle
    {
        if (! Gate::forUser($actor)->allows('receiveSample', $material)) {
            throw new AuthorizationException('Anda tidak berhak mengonfirmasi penerimaan sampel.');
        }

        $validated = Validator::make($data, [
            'received_quantity' => ['required', 'numeric', 'gt:0'],
            'received_condition' => ['required', Rule::in(array_keys(self::CONDITIONS))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        $stored = $this->evidence->store($data['photos'] ?? [], $material->id, RndMaterialSampleAttachment::KIND_RECEIPT, 'photos');

        try {
            return DB::transaction(function () use ($material, $actor, $validated, $stored): RndMaterialSampleCycle {
                $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($material->id);
                $cycle = RndMaterialSampleCycle::query()->lockForUpdate()->where('rnd_product_esb_material_id', $material->id)
                    ->where('status', MaterialSampleCycleStatus::HandedOver->value)->latest('cycle_number')->first();
                if (! $cycle) {
                    throw ValidationException::withMessages(['received_quantity' => 'Tidak ada sampel yang menunggu konfirmasi penerimaan.']);
                }

                $cycle->fill([
                    'status' => MaterialSampleCycleStatus::Received,
                    'received_quantity' => $validated['received_quantity'],
                    'received_condition' => $validated['received_condition'],
                    'receipt_notes' => $validated['notes'] ?? null,
                    'received_by' => $actor->id,
                    'received_at' => now(),
                ])->save();
                $this->evidence->attach($cycle, $stored, RndMaterialSampleAttachment::KIND_RECEIPT, $actor);

                $material->forceFill(['sourcing_status' => MaterialSourcingStatus::SampleReceived])->save();
                $this->audit->event($material, 'sample_received', $actor, from: MaterialSourcingStatus::SampleHandedOver->value, to: MaterialSourcingStatus::SampleReceived->value, metadata: ['cycle' => $cycle->cycle_number, 'condition' => $validated['received_condition']]);

                return $cycle;
            });
        } catch (Throwable $exception) {
            $this->evidence->discard($stored);

            throw $exception;
        }
    }
}
