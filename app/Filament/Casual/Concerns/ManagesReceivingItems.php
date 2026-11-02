<?php

namespace App\Filament\Casual\Concerns;

use App\Services\InboundGoodsReceiptQcService;

/**
 * Item QC behaviour shared by Receiving with PO and Tanpa PO: the "Sesuai/Bermasalah" row, batches,
 * and the detail modal in filament.casual.pages.partials.goods-receipt-item-detail-modal. Hosts own
 * `$items`, `$activeItemIndex`, and `$goodsReceiptDate`.
 */
trait ManagesReceivingItems
{
    public function addBatch(int $item): void
    {
        $this->items[$item]['batches'][] = ['batchNumber' => '', 'manufacturedDate' => '', 'expiredDate' => '', 'quantity' => 0, 'acceptedQty' => 0, 'holdQty' => 0, 'rejectedQty' => 0];
    }

    public function removeBatch(int $item, int $batch): void
    {
        unset($this->items[$item]['batches'][$batch]);
        $this->items[$item]['batches'] = array_values($this->items[$item]['batches']);
    }

    public function removeItemEvidencePhoto(int $itemIndex, int $photoIndex): void
    {
        array_splice($this->items[$itemIndex]['evidencePhotos'], $photoIndex, 1);
        $this->items[$itemIndex]['evidencePhotos'] = array_values($this->items[$itemIndex]['evidencePhotos']);
    }

    public function openItemDetail(int $index): void
    {
        $this->items[$index]['condition'] = 'problem';
        $this->activeItemIndex = $index;
        $this->dispatch('open-modal', 'item-detail');
    }

    public function closeItemDetail(): void
    {
        $this->activeItemIndex = null;
        $this->dispatch('close-modal', 'item-detail');
    }

    /**
     * docs/products/inventory/receiving-simplification/prd.md §7: switching back to "Sesuai" clears any exception
     * data entered while "Bermasalah" was selected, so a stale Hold/Rejected/rejection reason
     * never survives a condition change the user no longer intends.
     */
    public function setItemCondition(int $index, string $condition): void
    {
        $this->items[$index]['condition'] = $condition;

        if ($condition === 'ok') {
            $this->items[$index] = array_replace($this->items[$index], [
                'acceptedQty' => $this->items[$index]['physicalQty'], 'holdQty' => 0, 'rejectedQty' => 0,
                'colorResult' => 'pass', 'textureResult' => 'pass', 'packagingResult' => 'pass', 'contaminationResult' => 'pass',
                'temperatureCategory' => 'ambient', 'actualTemperature' => null, 'minTemperature' => null, 'maxTemperature' => null,
                'shelfLifeRequired' => false, 'batches' => [],
                'samplingRequired' => false, 'samplingMethod' => '', 'samplingResult' => 'pass', 'samplingNotes' => '',
                'quarantineLocation' => '', 'rejectionCategory' => '', 'rejectionReason' => '', 'evidencePhotos' => [],
            ]);
            $this->closeItemDetail();
        }
    }

    /** @return array<string, mixed> */
    public function itemQcPreview(int $itemIndex): array
    {
        return app(InboundGoodsReceiptQcService::class)->assessItem(
            $this->items[$itemIndex] ?? [],
            $this->goodsReceiptDate ?: now()->toDateString(),
        );
    }

    /** @return array{days: int, label: string, color: string}|null */
    public function itemExpiryStatus(int $itemIndex): ?array
    {
        $expiryDate = $this->items[$itemIndex]['expiryDate'] ?? '';

        if (blank($expiryDate)) {
            return null;
        }

        return app(InboundGoodsReceiptQcService::class)->expiryStatus(
            $expiryDate,
            $this->goodsReceiptDate ?: now()->toDateString(),
        );
    }

    /**
     * Server-side defense in depth: "Sesuai" always means the full qty is Accepted and the
     * exception fields are blank, regardless of whatever the client last held for them
     * (docs/products/inventory/receiving-simplification/prd.md §6-7).
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeOkItem(array $item): array
    {
        return $item['condition'] === 'ok' ? array_replace($item, [
            'acceptedQty' => $item['physicalQty'], 'holdQty' => 0, 'rejectedQty' => 0,
            'colorResult' => 'pass', 'textureResult' => 'pass', 'packagingResult' => 'pass', 'contaminationResult' => 'pass',
            'temperatureCategory' => 'ambient', 'shelfLifeRequired' => false, 'batches' => [],
            'samplingRequired' => false, 'samplingResult' => 'pass',
            'quarantineLocation' => '', 'rejectionCategory' => '', 'rejectionReason' => '', 'evidencePhotos' => [],
        ]) : $item;
    }

    /** @return array<string, array<int, string>> */
    private function itemRules(): array
    {
        return [
            'items.*.physicalQty' => ['nullable', 'numeric', 'min:0'], 'items.*.acceptedQty' => ['nullable', 'numeric', 'min:0'],
            'items.*.holdQty' => ['nullable', 'numeric', 'min:0'], 'items.*.rejectedQty' => ['nullable', 'numeric', 'min:0'],
            'items.*.measurementMethod' => ['required', 'in:count,weigh,measure'], 'items.*.tolerancePercentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.colorResult' => ['required', 'in:pass,fail,not_applicable'], 'items.*.textureResult' => ['required', 'in:pass,fail,not_applicable'],
            'items.*.packagingResult' => ['required', 'in:pass,fail,not_applicable'], 'items.*.contaminationResult' => ['required', 'in:pass,fail,not_applicable'],
            'items.*.temperatureCategory' => ['required', 'in:ambient,chilled,frozen'], 'items.*.actualTemperature' => ['nullable', 'numeric'],
            'items.*.minTemperature' => ['nullable', 'numeric'], 'items.*.maxTemperature' => ['nullable', 'numeric'],
            'items.*.minimumShelfLifePercentage' => ['required', 'numeric', 'min:0', 'max:100'], 'items.*.samplingMethod' => ['nullable', 'string', 'max:255'],
            'items.*.samplingResult' => ['required', 'in:pass,fail,pending'], 'items.*.samplingNotes' => ['nullable', 'string', 'max:1000'],
            'items.*.quarantineLocation' => ['nullable', 'string', 'max:255'], 'items.*.rejectionCategory' => ['nullable', 'in:document,quantity,quality,packaging,cold_chain,shelf_life,sampling,other'],
            'items.*.rejectionReason' => ['nullable', 'string', 'max:2000'], 'items.*.evidencePhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'], 'items.*.expiryDate' => ['nullable', 'date'],
            'items.*.batches' => ['array'],
            'items.*.batches.*.batchNumber' => ['nullable', 'string', 'max:255'], 'items.*.batches.*.manufacturedDate' => ['nullable', 'date'],
            'items.*.batches.*.expiredDate' => ['nullable', 'date'], 'items.*.batches.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.batches.*.acceptedQty' => ['nullable', 'numeric', 'min:0'], 'items.*.batches.*.holdQty' => ['nullable', 'numeric', 'min:0'],
            'items.*.batches.*.rejectedQty' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
