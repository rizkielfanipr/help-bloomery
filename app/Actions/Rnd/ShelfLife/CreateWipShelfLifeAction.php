<?php

namespace App\Actions\Rnd\ShelfLife;

use App\Enums\RndWipShelfLifeSource;
use App\Exceptions\Rnd\WipShelfLifeAlreadyExistsException;
use App\Models\RndProductEsbShelfLife;
use App\Models\User;
use App\Services\Rnd\ShelfLife\WipShelfLifeResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a missing WIP Shelf Life master from the Shelf Life menu or a Project
 * (docs/rnd-wip-shelf-life-prd.md §14.1). It never overwrites: an existing master (active or
 * inactive) raises `WipShelfLifeAlreadyExistsException`; a soft-deleted row for the same identity
 * is restored and refilled instead of duplicated. Local only — nothing is sent to ESB.
 *
 * The caller must have resolved the Product Detail ID server-side from the BOM catalog or the
 * Project's own WIP mapping; this Action re-checks permissions and validates the values. A
 * non-base unit of a known WIP Product is stored on the Product's base unit, so every unit of one
 * WIP shares a single master.
 */
class CreateWipShelfLifeAction
{
    public function __construct(
        private readonly WipShelfLifeInputValidator $validator,
        private readonly WipShelfLifeResolver $resolver,
    ) {}

    /**
     * @param  array{company_code?: string, esb_product_detail_id: int, product_code: ?string, product_name: string, shelf_life_value: int|float|string, shelf_life_unit: string, storage_condition: string, notes: ?string}  $data
     */
    public function execute(RndWipShelfLifeSource $source, array $data, User $actor): RndProductEsbShelfLife
    {
        foreach ($source->requiredPermissions() as $permission) {
            if (! $actor->can($permission)) {
                throw new AuthorizationException('Anda tidak berhak mengisi Shelf Life WIP.');
            }
        }

        $identity = $this->validator->identity($data);
        $identity['esb_product_detail_id'] = $this->resolver->baseProductDetailIds([$identity['esb_product_detail_id']], $identity['company_code'])[$identity['esb_product_detail_id']]
            ?? $identity['esb_product_detail_id'];
        $values = $this->validator->values($data);

        try {
            return DB::transaction(function () use ($identity, $values, $actor): RndProductEsbShelfLife {
                $existing = RndProductEsbShelfLife::withTrashed()
                    ->where('company_code', $identity['company_code'])
                    ->where('esb_product_detail_id', $identity['esb_product_detail_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null && ! $existing->trashed()) {
                    throw new WipShelfLifeAlreadyExistsException($existing);
                }

                if ($existing !== null) {
                    $existing->restore();
                    $existing->fill([...$identity, ...$values, 'is_active' => true, 'updated_by' => $actor->id])->save();

                    return $existing->refresh();
                }

                return RndProductEsbShelfLife::query()->create([
                    ...$identity,
                    ...$values,
                    'is_active' => true,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Lost a race with a concurrent create: report the record that won, never insert twice.
            throw new WipShelfLifeAlreadyExistsException(
                RndProductEsbShelfLife::query()
                    ->where('company_code', $identity['company_code'])
                    ->where('esb_product_detail_id', $identity['esb_product_detail_id'])
                    ->firstOrFail(),
            );
        }
    }
}
