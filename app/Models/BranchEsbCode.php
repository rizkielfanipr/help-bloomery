<?php

namespace App\Models;

use App\Enums\OrderingStockLocationMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchEsbCode extends Model
{
    protected $fillable = [
        'branch_id',
        'esb_branch_id',
        'esb_branch_code',
        'esb_comcode',
        'label',
        'is_active',
        'ordering_stock_location_mode',
        'ordering_stock_locations',
        'ordering_stock_locations_updated_at',
        'esb_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'esb_branch_id' => 'integer',
            'is_active' => 'boolean',
            'ordering_stock_location_mode' => OrderingStockLocationMode::class,
            'ordering_stock_locations' => 'array',
            'ordering_stock_locations_updated_at' => 'datetime',
            'esb_synced_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getEsbTokenAttribute(): string
    {
        return config('esb.tokens.'.$this->esb_comcode, '');
    }

    /**
     * The selected ESB inventory locations for Ordering Recommendation.
     *
     * `null` deliberately blocks stock-based recommendations. The global config remains a
     * temporary fallback for mappings created before per-branch settings were introduced.
     *
     * @return 'all'|list<string>|null
     */
    public function orderingStockLocationScope(): string|array|null
    {
        $mode = $this->ordering_stock_location_mode ?? OrderingStockLocationMode::Unconfigured;

        if ($mode === OrderingStockLocationMode::All) {
            return 'all';
        }

        if ($mode === OrderingStockLocationMode::Selected) {
            $values = collect($this->ordering_stock_locations ?? [])
                ->flatMap(fn (array $location): array => [
                    trim((string) ($location['key'] ?? '')),
                    trim((string) ($location['name'] ?? '')),
                ])
                ->filter()
                ->unique(fn (string $value): string => mb_strtolower($value))
                ->values()
                ->all();

            return $values === [] ? null : $values;
        }

        return config('ordering_recommendation.stock_location_scope');
    }
}
