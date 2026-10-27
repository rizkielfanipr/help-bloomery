<?php

namespace App\Models;

use App\Enums\MaterialSampleCycleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sample cycle (§16.1). Purchasing hand-over evidence and R&D receipt evidence are separate
 * attachment kinds and never substitute each other.
 */
class RndMaterialSampleCycle extends Model
{
    public const EVALUATION_APPROVE = 'approve';

    public const EVALUATION_RESAMPLE = 'resample';

    public const EVALUATION_REJECT = 'reject';

    protected $fillable = [
        'rnd_product_esb_material_id',
        'cycle_number',
        'material_sourcing_id',
        'status',
        'quantity',
        'uom_name',
        'handover_notes',
        'handed_over_by',
        'handed_over_at',
        'received_quantity',
        'received_condition',
        'receipt_notes',
        'received_by',
        'received_at',
        'evaluation',
        'evaluation_reason',
        'evaluated_by',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'cycle_number' => 'integer',
            'status' => MaterialSampleCycleStatus::class,
            'quantity' => 'decimal:4',
            'received_quantity' => 'decimal:4',
            'handed_over_at' => 'datetime',
            'received_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(RndProductEsbMaterial::class, 'rnd_product_esb_material_id');
    }

    public function supplierCandidate(): BelongsTo
    {
        return $this->belongsTo(MaterialSourcing::class, 'material_sourcing_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RndMaterialSampleAttachment::class);
    }

    public function handedOverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
