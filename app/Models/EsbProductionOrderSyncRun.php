<?php

namespace App\Models;

use App\Enums\EsbProductionOrderSyncMode;
use App\Enums\EsbProductionOrderSyncStatus;
use Database\Factories\EsbProductionOrderSyncRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One execution of the Production Order sync for one mapping and window
 * (docs/products/kitchen/production-schedule/prd.md §29.3, §30). Detail jobs report their outcome here and the
 * last one to finish closes the run; the conditional update makes that close happen once.
 */
class EsbProductionOrderSyncRun extends Model
{
    /** @use HasFactory<EsbProductionOrderSyncRunFactory> */
    use HasFactory, MassPrunable;

    protected $fillable = [
        'idempotency_key', 'active_key', 'branch_esb_code_id', 'branch_id', 'company_code', 'esb_branch_id', 'mode',
        'date_from', 'date_to', 'status', 'last_page', 'pages_fetched', 'headers_received', 'headers_created',
        'headers_updated', 'headers_unchanged', 'conflicts', 'details_expected', 'details_succeeded', 'details_failed',
        'attempts', 'error_class', 'error_code', 'error_summary', 'correlation_id', 'started_at', 'list_completed_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'mode' => EsbProductionOrderSyncMode::class,
            'status' => EsbProductionOrderSyncStatus::class,
            'date_from' => 'date',
            'date_to' => 'date',
            'esb_branch_id' => 'integer',
            'started_at' => 'datetime',
            'list_completed_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public static function windowKey(BranchEsbCode $mapping, EsbProductionOrderSyncMode $mode, string $from, string $to): string
    {
        return implode(':', ['kitchen-po', $mapping->id, $mode->value, $from, $to]);
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(BranchEsbCode::class, 'branch_esb_code_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Closes the run once the list is complete and every scheduled detail has an outcome.
     */
    public function finalizeIfComplete(): void
    {
        $this->refresh();

        if ($this->status !== EsbProductionOrderSyncStatus::Running
            || $this->list_completed_at === null
            || $this->details_succeeded + $this->details_failed < $this->details_expected) {
            return;
        }

        $status = $this->details_failed > 0 || $this->conflicts > 0
            ? EsbProductionOrderSyncStatus::Partial
            : EsbProductionOrderSyncStatus::Succeeded;

        $closed = static::query()
            ->whereKey($this->id)
            ->where('status', EsbProductionOrderSyncStatus::Running->value)
            ->update(['status' => $status->value, 'finished_at' => now(), 'active_key' => null, 'updated_at' => now()]);

        if ($closed === 1) {
            $this->refresh();
        }
    }

    public function recordDetailOutcome(bool $succeeded): void
    {
        static::query()->whereKey($this->id)->increment($succeeded ? 'details_succeeded' : 'details_failed');

        $this->finalizeIfComplete();
    }

    /**
     * Marks the run failed and frees its window. A run that already finished keeps its outcome.
     */
    public function markFailed(string $errorClass, ?string $errorCode, string $summary): void
    {
        static::query()
            ->whereKey($this->id)
            ->whereIn('status', [EsbProductionOrderSyncStatus::Queued->value, EsbProductionOrderSyncStatus::Running->value])
            ->update([
                'status' => EsbProductionOrderSyncStatus::Failed->value,
                'error_class' => mb_substr($errorClass, 0, 30),
                'error_code' => $errorCode === null ? null : mb_substr($errorCode, 0, 30),
                'error_summary' => mb_substr($summary, 0, 500),
                'finished_at' => now(),
                'active_key' => null,
                'updated_at' => now(),
            ]);

        $this->refresh();
    }

    /**
     * Run history only; snapshots are never pruned (docs/products/kitchen/production-schedule/prd.md §29.3, K12).
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('active_key')
            ->where('created_at', '<', now()->subDays((int) config('kitchen.production_orders.run_retention_days', 90)));
    }
}
