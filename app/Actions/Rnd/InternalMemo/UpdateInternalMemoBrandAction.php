<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Models\RndInternalMemo;
use App\Models\User;
use App\Services\Rnd\InternalMemo\InternalMemoBrandValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Changes a Memo's Brand (docs/rnd-internal-memo-brand-prd.md §8.5, §15.2). Metadata only: it
 * writes `brand_id`, `brand_name_snapshot`, and `updated_by` — never Menus, items, Minimum Orders,
 * attachments, BOM snapshots, or Branch rows — and never talks to ESB or queues a sync.
 */
class UpdateInternalMemoBrandAction
{
    public function __construct(private readonly InternalMemoBrandValidator $brands) {}

    public function execute(RndInternalMemo $memo, mixed $brandId, User $actor, ?string $periodMonth = null): RndInternalMemo
    {
        if (! $actor->can('update', $memo)) {
            throw new AuthorizationException('Anda tidak berhak mengubah Memo Internal ini.');
        }

        $brand = $this->brands->brand($brandId);
        $this->brands->ensureUniquePeriod($brand, $periodMonth ?? $memo->period_month->toDateString(), $memo->revision, $memo->id);

        $previousBrandId = $memo->brand_id;

        try {
            $memo->forceFill([
                'brand_id' => $brand->id,
                'brand_name_snapshot' => $brand->name,
                'updated_by' => $actor->id,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            throw $this->brands->duplicatePeriod($brand);
        }

        Log::info('rnd internal memo brand updated', [
            'memo_id' => $memo->id,
            'previous_brand_id' => $previousBrandId,
            'brand_id' => $brand->id,
            'user_id' => $actor->id,
        ]);

        return $memo;
    }
}
