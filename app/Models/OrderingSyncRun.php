<?php

namespace App\Models;

use App\Enums\OrderingSource;
use App\Enums\OrderingSyncStatus;
use Database\Factories\OrderingSyncRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ESB read of a source for a branch, company pair, and date (docs/ordering-recommendation-prd.md §11.1).
 */
class OrderingSyncRun extends Model
{
    /** @use HasFactory<OrderingSyncRunFactory> */
    use HasFactory;

    protected $fillable = [
        'idempotency_key', 'source', 'branch_id', 'company_code', 'esb_branch_code', 'period_from', 'period_to',
        'status', 'rows_received', 'rows_stored', 'pages_fetched', 'attempts', 'started_at', 'finished_at',
        'error_summary', 'correlation_id', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'source' => OrderingSource::class,
            'status' => OrderingSyncStatus::class,
            'period_from' => 'date',
            'period_to' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public static function idempotencyKey(OrderingSource $source, BranchEsbCode $pair, string $date): string
    {
        return implode(':', [$source->value, $pair->branch_id, $pair->esb_comcode, $pair->esb_branch_code, $date]);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
