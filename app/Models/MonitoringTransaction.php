<?php

namespace App\Models;

use App\Enums\MonitoringTransactionStatus;
use App\Services\Monitoring\MonitoringContext;
use Database\Factories\MonitoringTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Observed ESB mutation: a parent row per user action and one child row per HTTP mutation attempt
 * (docs/system-monitoring-prd.md §11). The domain record referenced by `local_model_type` +
 * `local_model_id` stays the source of truth.
 */
class MonitoringTransaction extends Model
{
    /** @use HasFactory<MonitoringTransactionFactory> */
    use HasFactory;

    /** Columns safe for index pages: excludes the sanitized payload summaries and timeline. */
    public const INDEX_COLUMNS = [
        'id', 'uuid', 'parent_id', 'environment', 'source_module', 'source_action', 'operation_type',
        'company_code', 'branch_id', 'local_model_type', 'local_model_id', 'external_reference',
        'correlation_id', 'status', 'attempt_count', 'actor_id', 'started_at', 'completed_at', 'duration_ms',
    ];

    protected $fillable = [
        'uuid',
        'parent_id',
        'environment',
        'source_module',
        'source_action',
        'operation_type',
        'target_key',
        'company_code',
        'branch_id',
        'local_model_type',
        'local_model_id',
        'external_reference',
        'correlation_id',
        'idempotency_key',
        'method',
        'endpoint_template',
        'status',
        'attempt_count',
        'mutation_sent',
        'http_status',
        'request_summary',
        'response_summary',
        'timeline',
        'payload_expired_at',
        'error_class',
        'error_code',
        'safe_error_message',
        'actor_id',
        'actor_role_snapshot',
        'deploy_commit',
        'started_at',
        'completed_at',
        'verified_at',
        'duration_ms',
        'reconciliation_outcome',
        'reconciliation_note',
        'reconciled_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => MonitoringTransactionStatus::class,
            'branch_id' => 'integer',
            'local_model_id' => 'integer',
            'attempt_count' => 'integer',
            'mutation_sent' => 'boolean',
            'http_status' => 'integer',
            'request_summary' => 'array',
            'response_summary' => 'array',
            'timeline' => 'array',
            'payload_expired_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @param Builder<MonitoringTransaction> $query */
    public function scopeParents(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /** @param Builder<MonitoringTransaction> $query */
    public function scopeForCurrentEnvironment(Builder $query): void
    {
        $query->where('environment', MonitoringContext::environment());
    }

    /** @param Builder<MonitoringTransaction> $query */
    public function scopeForLocalModel(Builder $query, Model $model): void
    {
        $query->where('local_model_type', $model->getMorphClass())->where('local_model_id', $model->getKey());
    }

    /**
     * A user can only see transactions for Branches they can access, unless they see all branches.
     *
     * @param  Builder<MonitoringTransaction>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->canAccessAllBranches()) {
            return;
        }

        $query->whereIn('branch_id', $user->accessibleBranchIds());
    }

    public function isParent(): bool
    {
        return $this->parent_id === null;
    }

    public function moduleLabel(): string
    {
        return self::moduleOptions()[$this->source_module] ?? str($this->source_module)->headline()->toString();
    }

    /** @return array<string, string> */
    public static function moduleOptions(): array
    {
        return [
            'rnd_product_release' => 'R&D Product Release',
            'rnd_bom' => 'R&D BOM',
            'bom_adjustment' => 'BOM Adjustment',
            'bulk_product' => 'Bulk Data Product',
            'bulk_promotion' => 'Bulk Data Promotion',
            'item_journal' => 'Item Journal',
            'goods_receipt' => 'Goods Receipt',
            'system' => 'System / Console',
        ];
    }
}
