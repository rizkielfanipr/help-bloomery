<?php

namespace App\Models;

use App\Enums\MaterialEsbSyncStatus;
use App\Enums\MaterialPricingStatus;
use App\Enums\MaterialReviewStatus;
use App\Enums\MaterialSourcingStatus;
use App\Enums\MonitoringTransactionStatus;
use Database\Factories\RndProductEsbMaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RndProductEsbMaterial extends Model
{
    /** @use HasFactory<RndProductEsbMaterialFactory> */
    use HasFactory;

    /** Raised from a Project R&D product's material list. */
    public const SOURCING_ORIGIN_PROJECT = 'project';

    /** Recorded directly in Sourcing Bahan, e.g. a sample sent without any Project. */
    public const SOURCING_ORIGIN_STANDALONE = 'standalone';

    public const STATUSES = [
        'draft' => 'Draft',
        'syncing' => 'Syncing',
        'synced' => 'Synced',
        'failed' => 'Failed',
        'unknown' => 'Needs Reconciliation',
    ];

    /**
     * The section an ESB category belongs to, judged by its name alone.
     */
    public static function sectionForCategoryName(?string $categoryName): string
    {
        $category = mb_strtolower(trim((string) $categoryName));

        return match (true) {
            str_contains($category, 'wip') => 'wip',
            str_contains($category, 'marketing') => 'marketing',
            str_contains($category, 'packaging'), str_contains($category, 'kemasan') => 'packaging',
            default => 'raw',
        };
    }

    public function materialSection(): string
    {
        $categorySection = self::sectionForCategoryName($this->category_name);
        $code = strtoupper(trim((string) $this->product_code));

        if ($this->material_section === 'marketing') {
            return 'marketing';
        }

        if ($categorySection === 'wip' || str_starts_with($code, 'BW')) {
            return 'wip';
        }

        if ($categorySection === 'marketing') {
            return 'marketing';
        }

        if ($categorySection === 'packaging' || str_starts_with($code, 'PKG') || str_starts_with($code, 'PAM')) {
            return 'packaging';
        }

        return 'raw';
    }

    protected $attributes = [
        'sourcing_origin' => self::SOURCING_ORIGIN_PROJECT,
    ];

    protected $fillable = [
        'rnd_project_product_id',
        'category_id',
        'category_name',
        'sub_category_id',
        'sub_category_name',
        'material_section',
        'uom_id',
        'uom_name',
        'product_code',
        'product_name',
        'sku',
        'conversion_factor',
        'base_price',
        'notes',
        'status',
        'esb_product_id',
        'esb_is_temp',
        'last_payload',
        'last_response',
        'sync_error',
        'synced_at',
        'created_by',
        'sourcing_status',
        'sourcing_origin',
        'esb_product_detail_id',
        'esb_product_code',
        'sourcing_selected_id',
        'rnd_reviewed_by',
        'rnd_reviewed_at',
        'rnd_note',
        'finance_reviewed_by',
        'finance_reviewed_at',
        'finance_note',
        'review_status',
        'submission_version',
        'esb_sync_status',
        'esb_sync_claimed_at',
        'esb_verified_at',
        'pricing_status',
        'sourcing_requested_at',
        'workflow_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
            'base_price' => 'decimal:4',
            'esb_is_temp' => 'boolean',
            'last_payload' => 'array',
            'last_response' => 'array',
            'synced_at' => 'datetime',
            'esb_product_detail_id' => 'integer',
            'sourcing_status' => MaterialSourcingStatus::class,
            'rnd_reviewed_at' => 'datetime',
            'finance_reviewed_at' => 'datetime',
            'review_status' => MaterialReviewStatus::class,
            'submission_version' => 'integer',
            'esb_sync_status' => MaterialEsbSyncStatus::class,
            'esb_sync_claimed_at' => 'datetime',
            'esb_verified_at' => 'datetime',
            'pricing_status' => MaterialPricingStatus::class,
            'sourcing_requested_at' => 'datetime',
            'workflow_completed_at' => 'datetime',
        ];
    }

    /**
     * Created through the Master Bahan workflow (docs/products/rnd/material-sourcing-lifecycle/prd.md). Rows
     * created before the workflow and standalone sourcing keep `review_status = null` and follow
     * the legacy flow without receiving review history they never had.
     */
    public function isInWorkflow(): bool
    {
        return $this->review_status !== null;
    }

    public function isWip(): bool
    {
        return $this->materialSection() === 'wip';
    }

    /** WIP never goes through supplier sourcing or samples (§10.2). */
    public function requiresSourcing(): bool
    {
        return ! $this->isWip();
    }

    /**
     * ESB state for display and guards: the workflow column when present, otherwise the legacy
     * `status` value. A legacy `synced` is not proof of verified mapping (see isVerifiedSynced).
     */
    public function effectiveEsbSyncStatus(): MaterialEsbSyncStatus
    {
        return $this->esb_sync_status ?? match ($this->status) {
            'synced' => MaterialEsbSyncStatus::Synced,
            'syncing' => MaterialEsbSyncStatus::Syncing,
            'failed' => MaterialEsbSyncStatus::Failed,
            'unknown' => MaterialEsbSyncStatus::NeedsReconciliation,
            default => MaterialEsbSyncStatus::NotReady,
        };
    }

    /** Product ID and every active unit's Product Detail ID were read back and verified (§13.3). */
    /**
     * Minutes after which an unfinished `syncing` claim is considered abandoned (the request or
     * worker died between the ESB call and the local result).
     */
    public const ESB_CLAIM_STALE_MINUTES = 15;

    /**
     * Grace period after which a `syncing` claim whose ESB transaction already finished is
     * reconcilable, leaving the normal run time to store its own result first.
     */
    public const ESB_CLAIM_FINISHED_GRACE_MINUTES = 2;

    /**
     * IT must reconcile: an uncertain outcome, or a `syncing` claim that never finished — either
     * because its ESB transaction already ended without a local result, or because it is stale.
     */
    public function needsEsbReconciliation(): bool
    {
        if ($this->effectiveEsbSyncStatus() === MaterialEsbSyncStatus::NeedsReconciliation) {
            return true;
        }
        if ($this->esb_sync_status !== MaterialEsbSyncStatus::Syncing || $this->esb_sync_claimed_at === null) {
            return false;
        }

        return $this->esb_sync_claimed_at->lte(now()->subMinutes(self::ESB_CLAIM_STALE_MINUTES))
            || ($this->esb_sync_claimed_at->lte(now()->subMinutes(self::ESB_CLAIM_FINISHED_GRACE_MINUTES))
                && $this->finishedEsbClaimTransaction() !== null);
    }

    /**
     * The monitoring transaction of the current `syncing` claim when its ESB request already ended
     * (its `external_reference` is the Product ID ESB returned, if any).
     */
    public function finishedEsbClaimTransaction(): ?MonitoringTransaction
    {
        if ($this->esb_sync_status !== MaterialEsbSyncStatus::Syncing || $this->esb_sync_claimed_at === null) {
            return null;
        }

        return MonitoringTransaction::query()->parents()
            ->where('idempotency_key', "material:{$this->id}:v{$this->submission_version}")
            ->where('created_at', '>=', $this->esb_sync_claimed_at)
            ->whereNotIn('status', [MonitoringTransactionStatus::Pending->value, MonitoringTransactionStatus::Processing->value])
            ->latest('id')
            ->first();
    }

    public function isVerifiedSynced(): bool
    {
        return $this->esb_sync_status === MaterialEsbSyncStatus::Synced && $this->esb_verified_at !== null;
    }

    /**
     * A standalone sourcing has no Project product, ESB category or UOM ID; it only carries the
     * supplier/sample review through the same R&D and Finance approval.
     */
    public function isStandalone(): bool
    {
        return $this->sourcing_origin === self::SOURCING_ORIGIN_STANDALONE;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RndProjectProduct::class, 'rnd_project_product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function units(): HasMany
    {
        return $this->hasMany(RndProductEsbMaterialUnit::class)
            ->orderByDesc('is_base')
            ->orderBy('id');
    }

    public function sourcings(): HasMany
    {
        return $this->hasMany(MaterialSourcing::class);
    }

    public function selectedSourcing(): BelongsTo
    {
        return $this->belongsTo(MaterialSourcing::class, 'sourcing_selected_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(RndMaterialReview::class)->orderByDesc('submission_version');
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(RndMaterialReview::class)->latestOfMany('submission_version');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(RndMaterialAuditLog::class)->latest('created_at')->latest('id');
    }

    public function sampleCycles(): HasMany
    {
        return $this->hasMany(RndMaterialSampleCycle::class)->orderByDesc('cycle_number');
    }

    public function pricingRequests(): HasMany
    {
        return $this->hasMany(RndMaterialPricingRequest::class)->latest('id');
    }

    public function sourcingApprovals(): HasMany
    {
        return $this->hasMany(MaterialSourcingApproval::class)->latest();
    }

    public function rndReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rnd_reviewed_by');
    }

    public function financeReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_reviewed_by');
    }

    public function toEsbPayload(): array
    {
        return [
            'categoryID' => $this->category_id,
            'subCategoryID' => $this->sub_category_id,
            'productCode' => $this->product_code,
            'productName' => $this->product_name,
            'requestable' => true,
            'purchasable' => true,
            'saleable' => false,
            'receiptTolerance' => 0,
            'notes' => $this->notes,
            'vat' => false,
            'bomID' => null,
            'flagLuxuryItem' => 0,
            'coretaxProductCodeID' => null,
            'pushToGoApp' => false,
            'productDetails' => $this->payloadUnits(),
            'customFields' => [
                'field1' => null,
                'field2' => null,
                'field3' => null,
                'field4' => null,
                'field5' => null,
            ],
        ];
    }

    private function payloadUnits(): array
    {
        $units = $this->units()->get();

        if ($units->isEmpty()) {
            return [[
                'uomID' => $this->uom_id,
                'basePrice' => (float) $this->base_price,
                'sku' => $this->sku,
                'cubication' => 0,
                'weight' => 0,
                'qty' => 1,
                'isStock' => true,
                'isPurchase' => true,
                'isTransfer' => true,
                'isSales' => true,
                'isBase' => true,
                'menuID' => null,
                'flagActive' => true,
            ]];
        }

        return $units->map(function (RndProductEsbMaterialUnit $unit): array {
            $payload = [
                'uomID' => $unit->uom_id,
                'basePrice' => (float) $unit->base_price,
                'sku' => $unit->sku,
                'cubication' => 0,
                'weight' => 0,
                'qty' => (float) $unit->conversion_factor,
                'isStock' => $unit->is_stock,
                'isPurchase' => $unit->is_purchase,
                'isTransfer' => $unit->is_transfer,
                'isSales' => $unit->is_sales || $unit->is_base,
                'isBase' => $unit->is_base,
                'menuID' => null,
                'flagActive' => $unit->flag_active,
            ];

            if ($unit->esb_product_detail_id) {
                $payload['productDetailID'] = $unit->esb_product_detail_id;
            }

            return $payload;
        })->values()->all();
    }
}
