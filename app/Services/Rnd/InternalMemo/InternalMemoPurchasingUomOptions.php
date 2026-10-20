<?php

namespace App\Services\Rnd\InternalMemo;

use Illuminate\Support\Collection;

class InternalMemoPurchasingUomOptions
{
    /** @param array<string, mixed>|null $snapshot
     * @return array<int, string>
     */
    public function fromSnapshot(?array $snapshot): array
    {
        if ($snapshot === null) {
            return [];
        }

        $details = is_array($snapshot['productDetails'] ?? null)
            ? $snapshot['productDetails']
            : array_filter([
                $snapshot['matchedProductDetail'] ?? null,
                $snapshot['purchaseProductDetail'] ?? null,
            ], 'is_array');

        return collect($details)
            ->filter(fn (mixed $detail): bool => is_array($detail) && (bool) ($detail['flagActive'] ?? true))
            ->mapWithKeys(function (array $detail): array {
                $uomId = (int) ($detail['uomID'] ?? 0);
                $uomName = trim((string) ($detail['uomName'] ?? $detail['unit'] ?? ''));

                return $uomId > 0 && $uomName !== '' ? [$uomId => $uomName] : [];
            })
            ->sort()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>|null>  $snapshots
     * @return array<int, string>
     */
    public function fromSnapshots(Collection $snapshots): array
    {
        $options = $snapshots->reduce(
            fn (array $options, ?array $snapshot): array => $options + $this->fromSnapshot($snapshot),
            [],
        );

        return collect($options)->sort()->all();
    }
}
