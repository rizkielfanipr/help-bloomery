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

            return $batch + ['shelfLifePercentage' => $percentage];
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

    public function documentsPass(array $document): bool
    {
        return ($document['invoiceStatus'] ?? '') === 'received'
            && collect(['poDocumentMatch', 'deliveryDocumentMatch', 'invoiceDocumentMatch', 'priceMatch'])
                ->every(fn (string $key): bool => $this->truthy($document[$key] ?? false));
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
