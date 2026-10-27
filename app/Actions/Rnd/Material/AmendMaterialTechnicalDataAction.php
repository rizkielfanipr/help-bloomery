<?php

namespace App\Actions\Rnd\Material;

use App\Models\RndProductEsbMaterial;
use App\Models\RndProductEsbMaterialUnit;
use App\Models\User;
use App\Services\Rnd\Material\MaterialAuditLogger;
use App\Services\Rnd\Material\MaterialSnapshot;
use App\Services\Rnd\Material\MaterialSubmissionValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * IT corrects technical fields during review (§12.4): category/subcategory and unit UOM, SKU,
 * conversion factor, flags, and active state. Name, code, and business meaning are returned to
 * R&D instead. Each changed field is audited with before/after and reason, and the review
 * snapshot/hash is explicitly updated so the approval covers the corrected data.
 */
class AmendMaterialTechnicalDataAction
{
    use ClaimedReview;

    private const MATERIAL_FIELDS = ['category_id', 'category_name', 'sub_category_id', 'sub_category_name'];

    private const UNIT_FIELDS = ['uom_id', 'uom_name', 'sku', 'conversion_factor', 'is_base', 'is_stock', 'is_purchase', 'is_transfer', 'is_sales', 'flag_active'];

    public function __construct(
        private readonly MaterialSnapshot $snapshot,
        private readonly MaterialSubmissionValidator $validator,
        private readonly MaterialAuditLogger $audit,
    ) {}

    /**
     * @param  array{category_id?: int, category_name?: ?string, sub_category_id?: int, sub_category_name?: ?string, units?: array<int, array<string, mixed>>}  $changes  units keyed by unit ID
     *
     * @throws AuthorizationException|ValidationException
     */
    public function execute(RndProductEsbMaterial $material, User $actor, array $changes, string $reason, ?int $expectedVersion = null): int
    {
        if (! Gate::forUser($actor)->allows('amendTechnical', $material)) {
            throw new AuthorizationException('Anda tidak berhak mengubah data teknis Master Bahan.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan perubahan teknis wajib diisi minimal 10 karakter.']);
        }

        return DB::transaction(function () use ($material, $actor, $changes, $reason, $expectedVersion): int {
            [$material, $review] = $this->lockClaimedReview($material, $actor, $expectedVersion);
            $count = 0;

            foreach (Arr::only($changes, self::MATERIAL_FIELDS) as $field => $value) {
                if ((string) $material->{$field} !== (string) $value) {
                    $this->audit->fieldChange($material, $field, $material->{$field}, $value, $actor, $reason, $review);
                    $material->{$field} = $value;
                    $count++;
                }
            }
            $material->save();

            foreach ((array) ($changes['units'] ?? []) as $unitId => $unitChanges) {
                /** @var RndProductEsbMaterialUnit|null $unit */
                $unit = $material->units()->whereKey($unitId)->first();
                if (! $unit) {
                    throw ValidationException::withMessages(['units' => 'Unit tidak ditemukan pada bahan ini.']);
                }

                foreach (Arr::only((array) $unitChanges, self::UNIT_FIELDS) as $field => $value) {
                    $before = $unit->{$field};
                    $differs = is_bool($before) ? $before !== (bool) $value : (is_numeric($before) && is_numeric($value) ? abs((float) $before - (float) $value) > 0.000001 : (string) $before !== (string) $value);
                    if ($differs && $field === 'sku' && RndProductEsbMaterialUnit::query()->where('sku', $value)->whereKeyNot($unit->id)->exists()) {
                        throw ValidationException::withMessages(['units' => "SKU {$value} sudah digunakan unit lain."]);
                    }
                    if ($differs) {
                        $this->audit->fieldChange($material, "units.{$unit->uom_name}.{$field}", $before, $value, $actor, $reason, $review);
                        $unit->{$field} = is_bool($before) ? (bool) $value : $value;
                        $count++;
                    }
                }
                $unit->save();
            }

            $material->unsetRelation('units');
            $this->validator->validate($material);

            if ($count > 0) {
                $snapshot = $this->snapshot->build($material);
                $review->forceFill(['snapshot' => $snapshot, 'snapshot_hash' => $this->snapshot->hash($snapshot)])->save();
                $this->audit->event($material, 'review_snapshot_updated', $actor, $reason, metadata: ['changed_fields' => $count], review: $review);
            }

            return $count;
        });
    }
}
