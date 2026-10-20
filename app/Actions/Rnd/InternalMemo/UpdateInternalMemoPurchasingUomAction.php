<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMaterial;
use App\Models\User;
use App\Services\Rnd\InternalMemo\InternalMemoItemIdentity;
use App\Services\Rnd\InternalMemo\InternalMemoPurchasingUomOptions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class UpdateInternalMemoPurchasingUomAction
{
    public function __construct(private readonly InternalMemoPurchasingUomOptions $options) {}

    public function execute(RndInternalMemo $memo, string $scopedIdentityKey, int $uomId, User $actor): int
    {
        if (! $actor->can('update', $memo)) {
            throw new AuthorizationException('Anda tidak berhak mengubah Memo Internal ini.');
        }

        [$scope, $identityKey] = InternalMemoItemIdentity::parseScopedKey($scopedIdentityKey);

        if (($extraProductId = InternalMemoItemIdentity::extraProductIdFromKey($identityKey)) !== null) {
            $extra = $memo->extraProducts()->whereKey($extraProductId)->firstOrFail();
            $uomName = $this->validatedUomName($this->options->fromSnapshot($extra->product_detail_snapshot), $uomId);

            return $extra->update(['purchasing_uom_id' => $uomId, 'purchasing_uom_name' => $uomName]) ? 1 : 0;
        }

        $materials = RndInternalMemoMaterial::query()
            ->whereIn('rnd_internal_memo_menu_id', $memo->menus()->select('id'))
            ->get()
            ->filter(function (RndInternalMemoMaterial $material) use ($identityKey, $scope): bool {
                $key = InternalMemoItemIdentity::key($material->esb_product_detail_id, $material->esb_product_id, $material->product_code, $material->product_name, $material->uom_name);

                return $key === $identityKey
                    && ($scope === null || InternalMemoItemIdentity::scopeForDepth((int) $material->depth) === $scope);
            });

        abort_if($materials->isEmpty(), 404);
        $uomName = $this->validatedUomName($this->options->fromSnapshots($materials->pluck('product_detail_snapshot')), $uomId);

        foreach ($materials as $material) {
            $material->update(['purchasing_uom_id' => $uomId, 'purchasing_uom_name' => $uomName]);
        }

        return $materials->count();
    }

    /** @param array<int, string> $options */
    private function validatedUomName(array $options, int $uomId): string
    {
        if (! array_key_exists($uomId, $options)) {
            throw ValidationException::withMessages(['purchaseUom' => 'UOM Purchasing tidak terdaftar atau sudah tidak aktif untuk item ini.']);
        }

        return $options[$uomId];
    }
}
