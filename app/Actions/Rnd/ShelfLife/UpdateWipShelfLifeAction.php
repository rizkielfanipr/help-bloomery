<?php

namespace App\Actions\Rnd\ShelfLife;

use App\Models\RndProductEsbShelfLife;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edits, deactivates, or reactivates an existing WIP Shelf Life master — Shelf Life menu only
 * (docs/rnd-wip-shelf-life-prd.md §14.2, §14.4). Only the Shelf Life allowlist can change; the
 * identity never does. The row is locked inside the transaction and `updated_by` records the actor;
 * the model's activity log keeps the before/after diff. Local only — nothing is sent to ESB.
 */
class UpdateWipShelfLifeAction
{
    private const REQUIRED_PERMISSION = 'manage wip shelf life';

    public function __construct(private readonly WipShelfLifeInputValidator $validator) {}

    /**
     * @param  array{shelf_life_value: int|float|string, shelf_life_unit: string, storage_condition: string, notes: ?string}  $data
     */
    public function execute(RndProductEsbShelfLife $master, array $data, User $actor): RndProductEsbShelfLife
    {
        $this->authorize($actor);
        $values = $this->validator->values($data);

        return $this->lockedUpdate($master, fn (RndProductEsbShelfLife $locked): array => $values, $actor);
    }

    public function setActive(RndProductEsbShelfLife $master, bool $isActive, User $actor): RndProductEsbShelfLife
    {
        $this->authorize($actor);

        return $this->lockedUpdate($master, fn (RndProductEsbShelfLife $locked): array => ['is_active' => $isActive], $actor);
    }

    private function authorize(User $actor): void
    {
        if (! $actor->can(self::REQUIRED_PERMISSION)) {
            throw new AuthorizationException('Anda tidak berhak mengubah master Shelf Life WIP.');
        }
    }

    /**
     * @param  callable(RndProductEsbShelfLife): array<string, mixed>  $changes
     */
    private function lockedUpdate(RndProductEsbShelfLife $master, callable $changes, User $actor): RndProductEsbShelfLife
    {
        return DB::transaction(function () use ($master, $changes, $actor): RndProductEsbShelfLife {
            $locked = RndProductEsbShelfLife::query()->whereKey($master->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->esb_product_detail_id === null) {
                throw ValidationException::withMessages(['esb_product_detail_id' => 'Data Shelf Life Menu lama tidak dapat diubah sebagai master WIP.']);
            }

            $locked->fill([...$changes($locked), 'updated_by' => $actor->id])->save();

            return $locked->refresh();
        });
    }
}
