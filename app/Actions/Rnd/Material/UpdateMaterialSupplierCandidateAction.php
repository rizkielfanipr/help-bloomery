<?php

namespace App\Actions\Rnd\Material;

use App\Enums\MaterialSourcingStatus;
use App\Models\MaterialSourcing;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateMaterialSupplierCandidateAction
{
    private const WORKFLOW_EDITABLE = [
        MaterialSourcingStatus::WaitingPurchasing,
        MaterialSourcingStatus::SourcingInProgress,
        MaterialSourcingStatus::ResampleRequested,
    ];

    public function __construct(private readonly MaterialAuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{candidate: MaterialSourcing, previous_attachment: ?string}
     *
     * @throws AuthorizationException|ValidationException
     */
    public function execute(MaterialSourcing $candidate, User $actor, array $data): array
    {
        if (! $actor->can('submit material sourcing')) {
            throw new AuthorizationException('Anda tidak berhak mengelola sourcing bahan.');
        }

        $validated = Validator::make($data, [
            'supplier_name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'moq' => ['nullable', 'string', 'max:255'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment_path' => ['nullable', 'string', 'max:2048'],
        ])->after(function ($validator) use ($data): void {
            $path = $data['attachment_path'] ?? null;
            if ($path !== null && $path !== ''
                && (! str_starts_with($path, 'material-sourcing/') || ! Storage::disk('b2')->exists($path))) {
                $validator->errors()->add('attachment_path', 'Lampiran supplier tidak valid.');
            }
        })->validate();

        return DB::transaction(function () use ($candidate, $actor, $validated): array {
            $candidate = MaterialSourcing::query()->lockForUpdate()->findOrFail($candidate->id);
            $material = RndProductEsbMaterial::query()->lockForUpdate()->findOrFail($candidate->rnd_product_esb_material_id);

            if ($material->isInWorkflow() && ! in_array($material->sourcing_status, self::WORKFLOW_EDITABLE, true)) {
                throw ValidationException::withMessages([
                    'supplier_name' => 'Supplier tidak dapat diubah setelah sampel diserahkan, disetujui, atau ditolak R&D.',
                ]);
            }

            $previousAttachment = $candidate->attachment_path;
            $candidate->fill($validated)->save();

            if ($material->isInWorkflow()) {
                $this->audit->event(
                    $material,
                    'supplier_candidate_updated',
                    $actor,
                    metadata: ['material_sourcing_id' => $candidate->id],
                );
            } else {
                $material->forceFill([
                    'sourcing_status' => MaterialSourcingStatus::PendingRndReview,
                    'sourcing_selected_id' => null,
                    'rnd_reviewed_by' => null,
                    'rnd_reviewed_at' => null,
                    'rnd_note' => null,
                    'finance_reviewed_by' => null,
                    'finance_reviewed_at' => null,
                    'finance_note' => null,
                ])->save();
            }

            return ['candidate' => $candidate, 'previous_attachment' => $previousAttachment];
        });
    }

    public function canEdit(MaterialSourcing $candidate, User $actor): bool
    {
        if (! $actor->can('submit material sourcing')) {
            return false;
        }

        $material = $candidate->material;

        return ! $material?->isInWorkflow()
            || in_array($material->sourcing_status, self::WORKFLOW_EDITABLE, true);
    }
}
