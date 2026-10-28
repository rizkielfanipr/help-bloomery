<?php

namespace App\Models;

use App\Enums\MaterialPricingStatus;
use Database\Factories\RndMaterialPricingRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Finance Pricelist queue item (§17, §19.4). `offer_price` is the supplier offer kept as a
 * reference only; `price` is the official Pricelist value and only counts once verified.
 */
class RndMaterialPricingRequest extends Model
{
    /** @use HasFactory<RndMaterialPricingRequestFactory> */
    use HasFactory;

    public const ORIGIN_SOURCING = 'sourcing';

    public const ORIGIN_WIP = 'wip';

    public const VERIFICATION_MANUAL = 'manual';

    protected $fillable = [
        'uuid',
        'rnd_product_esb_material_id',
        'esb_product_id',
        'product_detail_id',
        'uom_id',
        'uom_name',
        'origin',
        'rnd_material_sample_cycle_id',
        'material_sourcing_id',
        'supplier_name',
        'offer_price',
        'revision',
        'eligibility_key',
        'active_key',
        'status',
        'company_code',
        'branch_scope',
        'price',
        'effective_date',
        'finance_notes',
        'verification_method',
        'external_reference',
        'idempotency_key',
        'request_summary',
        'response_summary',
        'failure_reason',
        'conflict_reason',
        'drafted_by',
        'drafted_at',
        'verified_by',
        'verified_at',
        'queued_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MaterialPricingStatus::class,
            'esb_product_id' => 'integer',
            'product_detail_id' => 'integer',
            'uom_id' => 'integer',
            'offer_price' => 'decimal:4',
            'price' => 'decimal:4',
            'revision' => 'integer',
            'effective_date' => 'date',
            'request_summary' => 'array',
            'response_summary' => 'array',
            'drafted_at' => 'datetime',
            'verified_at' => 'datetime',
            'queued_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(RndProductEsbMaterial::class, 'rnd_product_esb_material_id');
    }

    public function sampleCycle(): BelongsTo
    {
        return $this->belongsTo(RndMaterialSampleCycle::class, 'rnd_material_sample_cycle_id');
    }

    public function supplierCandidate(): BelongsTo
    {
        return $this->belongsTo(MaterialSourcing::class, 'material_sourcing_id');
    }

    public function drafter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'drafted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
