<?php

namespace App\Models;

use App\Enums\MaterialReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One IT review submission version of a Master Bahan with the canonical snapshot that was
 * submitted (docs/material-sourcing-lifecycle-prd.md §19.1). An approval is only valid for the
 * exact `submission_version` + `snapshot_hash` it was given for.
 */
class RndMaterialReview extends Model
{
    public const DECISION_APPROVE_CREATE = 'approve_create';

    public const DECISION_APPROVE_LINK = 'approve_link';

    public const DECISION_RETURN = 'return_for_revision';

    public const DECISION_CANCEL = 'cancel';

    protected $fillable = [
        'rnd_product_esb_material_id',
        'submission_version',
        'active_material_id',
        'status',
        'snapshot',
        'snapshot_hash',
        'submitted_by',
        'submitted_at',
        'claimed_by',
        'claimed_at',
        'reviewed_by',
        'reviewed_at',
        'decision',
        'reason',
        'linked_esb_product_id',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'submission_version' => 'integer',
            'status' => MaterialReviewStatus::class,
            'snapshot' => 'array',
            'submitted_at' => 'datetime',
            'claimed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'consumed_at' => 'datetime',
            'linked_esb_product_id' => 'integer',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(RndProductEsbMaterial::class, 'rnd_product_esb_material_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
