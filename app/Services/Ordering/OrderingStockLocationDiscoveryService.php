<?php

namespace App\Services\Ordering;

use App\Models\BranchEsbCode;
use App\Services\EsbStockMovementService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class OrderingStockLocationDiscoveryService
{
    public function __construct(private readonly EsbStockMovementService $stockMovement) {}

    /**
     * @return list<array{fingerprint: string, key: string, name: string}>
     */
    public function discover(BranchEsbCode $pair, ?string $from = null, ?string $to = null): array
    {
        $end = $to ? CarbonImmutable::parse($to) : CarbonImmutable::yesterday();
        $start = $from ? CarbonImmutable::parse($from) : $end->subDays(29);

        $locations = [];
        foreach ($this->stockMovement->movements(
            $pair,
            $start->toDateString(),
            $end->toDateString(),
            (string) config('ordering_recommendation.flag_unit'),
        ) as $row) {
            $id = trim((string) ($row['locationID'] ?? $row['locationId'] ?? ''));
            $name = Str::squish((string) ($row['location'] ?? $row['locationName'] ?? ''));
            $key = $id !== '' && $id !== '0' ? $id : $name;
            if ($key === '' || $name === '') {
                continue;
            }

            $fingerprint = hash('sha256', mb_strtolower($key).'|'.mb_strtolower($name));
            $locations[$fingerprint] = ['fingerprint' => $fingerprint, 'key' => $key, 'name' => $name];
        }

        return collect($locations)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }
}
