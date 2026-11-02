<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class InboundGoodsReceiptQcService
{
    /** @return array<string, mixed> */
    public function assessItem(array $item, string $receiptDate): array
    {
        $physical = (float) ($item['physicalQty'] ?? 0);
        $expected = (float) ($item['outstandingQty'] ?? 0);
        $variance = $expected > 0 ? abs($physical - $expected) / $expected * 100 : 0;
        $quantityResult = $variance <= (float) ($item['tolerancePercentage'] ?? 0) ? 'pass' : 'fail';
        $visualResult = collect(['colorResult', 'textureResult', 'packagingResult', 'contaminationResult'])
            ->every(fn (string $key): bool => in_array($item[$key] ?? 'pass', ['pass', 'not_applicable'], true)) ? 'pass' : 'fail';

        $coldChainResult = 'not_applicable';
        if (($item['temperatureCategory'] ?? 'ambient') !== 'ambient') {
            $temperature = $item['actualTemperature'] ?? null;
            $minimum = $item['minTemperature'] ?? null;
            $maximum = $item['maxTemperature'] ?? null;
            $coldChainResult = is_numeric($temperature) && is_numeric($minimum) && is_numeric($maximum)
                && (float) $temperature >= (float) $minimum && (float) $temperature <= (float) $maximum ? 'pass' : 'fail';
        }

        $batches = collect($item['batches'] ?? [])->map(function (array $batch) use ($receiptDate): array {
            $percentage = $this->remainingShelfLifePercentage(
                $batch['manufacturedDate'] ?? null,
                $batch['expiredDate'] ?? null,
                $receiptDate,
            );

            return array_replace($batch, ['shelfLifePercentage' => $percentage]);
        })->all();
        $minimumShelfLife = (float) ($item['minimumShelfLifePercentage'] ?? 80);
        $shelfLifeResult = ! $this->truthy($item['shelfLifeRequired'] ?? false) ? 'not_applicable' : (
            $batches !== [] && collect($batches)->every(fn (array $batch): bool => $batch['shelfLifePercentage'] !== null
                && $batch['shelfLifePercentage'] >= $minimumShelfLife) ? 'pass' : 'fail'
        );
        $samplingResult = ! $this->truthy($item['samplingRequired'] ?? false)
            ? 'not_applicable' : ($item['samplingResult'] ?? 'pending');

        return [
            'variancePercentage' => round($variance, 4), 'quantityResult' => $quantityResult,
            'visualResult' => $visualResult, 'coldChainResult' => $coldChainResult,
            'shelfLifeResult' => $shelfLifeResult, 'samplingResult' => $samplingResult,
            'batches' => $batches,
            'canAccept' => $quantityResult === 'pass' && $visualResult === 'pass'
                && in_array($coldChainResult, ['pass', 'not_applicable'], true)
                && in_array($shelfLifeResult, ['pass', 'not_applicable'], true)
                && in_array($samplingResult, ['pass', 'not_applicable'], true),
        ];
    }

    /**
     * docs/products/inventory/receiving-simplification/prd.md §5.2: a single unified document (Surat Jalan or
     * Invoice) replaces the old delivery+invoice duality, so there is no longer a "belum
     * diterima" state to gate on — whichever document type was chosen is, by definition, the
     * document of record for this receipt.
     */
    public function documentsPass(array $document): bool
    {
        return collect(['poDocumentMatch', 'documentMatch', 'priceMatch'])
            ->every(fn (string $key): bool => $this->truthy($document[$key] ?? false));
    }

    /**
     * docs/products/inventory/receiving-simplification/prd.md §9: a plain remaining-days readout shown on every
     * product row regardless of condition — independent of the detailed per-batch shelf-life
     * percentage workflow reserved for "Bermasalah" items.
     *
     * @return array{days: int, label: string, color: string}
     */
    public function expiryStatus(string $expiredDate, string $receiptDate): array
    {
        $expired = CarbonImmutable::parse($expiredDate)->startOfDay();
        $received = CarbonImmutable::parse($receiptDate)->startOfDay();
        $days = (int) $received->diffInDays($expired, false);

        return [
            'days' => $days,
            'label' => match (true) {
                $days > 0 => "{$days} hari lagi",
                $days === 0 => 'Hari ini',
                default => 'Lewat '.abs($days).' hari',
            },
            // §9 "hijau untuk aman; amber untuk mendekati expired; merah untuk expired" does not
            // specify the amber threshold — 7 days is a conservative, documented default.
            'color' => match (true) {
                $days < 0 => 'red',
                $days <= 7 => 'amber',
                default => 'green',
            },
        ];
    }

    /**
     * Item-level QC rules shared by Receiving with PO and Tanpa PO, keyed by the item field the
     * message belongs to. An item without an outstanding qty has no PO to compare against yet.
     *
     * @param  array<string, mixed>  $item  An item already merged with assessItem().
     * @return array<string, string>
     */
    public function itemViolations(array $item, bool $documentsPass): array
    {
        $errors = [];
        $physical = (float) $item['physicalQty'];
        $accepted = (float) $item['acceptedQty'];
        $hold = (float) $item['holdQty'];
        $rejected = (float) $item['rejectedQty'];
        if (abs($accepted + $hold + $rejected - $physical) > 0.0001) {
            $errors['physicalQty'] = 'Accepted + Hold + Rejected harus sama dengan qty fisik.';
        }
        if (($item['outstandingQty'] ?? null) !== null && $accepted > (float) $item['outstandingQty']) {
            $errors['acceptedQty'] = 'Qty Accepted melebihi sisa PO.';
        }
        if ($accepted > 0 && (! $documentsPass || ! $item['canAccept'])) {
            $errors['acceptedQty'] = 'Qty tidak dapat Accepted karena pemeriksaan QC gagal atau pending.';
        }
        if (($hold > 0 || $rejected > 0) && (blank($item['quarantineLocation']) || blank($item['rejectionCategory']) || blank($item['rejectionReason']) || empty($item['evidencePhotos']))) {
            $errors['rejectionReason'] = 'Lokasi karantina, kategori, alasan, dan foto wajib untuk Hold/Rejected.';
        }
        if (($item['temperatureCategory'] ?? 'ambient') !== 'ambient' && (! is_numeric($item['actualTemperature']) || ! is_numeric($item['minTemperature']) || ! is_numeric($item['maxTemperature']))) {
            $errors['actualTemperature'] = 'Suhu aktual dan rentang cold chain wajib diisi.';
        }
        if (! empty($item['shelfLifeRequired'])) {
            if ($item['batches'] === []) {
                $errors['batches'] = 'Minimal satu batch wajib diisi.';
            } else {
                foreach (['quantity' => $physical, 'acceptedQty' => $accepted, 'holdQty' => $hold, 'rejectedQty' => $rejected] as $field => $expected) {
                    if (abs((float) collect($item['batches'])->sum($field) - $expected) > 0.0001) {
                        $errors['batches'] = 'Total pembagian qty batch harus sama dengan qty item.';
                    }
                }
            }
        }

        return $errors;
    }

    public function disposition(float $accepted, float $hold, float $rejected): string
    {
        if ($accepted > 0 && ($hold > 0 || $rejected > 0)) {
            return 'partial';
        }

        if ($accepted > 0) {
            return 'accepted';
        }

        return $rejected > 0 && $hold <= 0 ? 'rejected' : 'hold';
    }

    public function demeritPoints(string $category): int
    {
        return match ($category) {
            'cold_chain', 'shelf_life' => 20,
            'sampling' => 15,
            'document', 'quantity', 'quality', 'packaging' => 10,
            default => 5,
        };
    }

    private function remainingShelfLifePercentage(?string $manufacturedDate, ?string $expiredDate, string $receiptDate): ?float
    {
        if (! filled($manufacturedDate) || ! filled($expiredDate)) {
            return null;
        }

        $manufactured = CarbonImmutable::parse($manufacturedDate)->startOfDay();
        $expired = CarbonImmutable::parse($expiredDate)->startOfDay();
        $received = CarbonImmutable::parse($receiptDate)->startOfDay();
        $totalDays = $manufactured->diffInDays($expired, false);
        $remainingDays = $received->diffInDays($expired, false);
        if ($totalDays <= 0 || $remainingDays < 0 || $received->lessThan($manufactured)) {
            return 0;
        }

        return round(min(100, $remainingDays / $totalDays * 100), 2);
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }
}
