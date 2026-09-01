<?php

namespace App\Models;

use Database\Factories\BulkProductSubmissionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkProductSubmissionItem extends Model
{
    /** @use HasFactory<BulkProductSubmissionItemFactory> */
    use HasFactory;

    protected $fillable = [
        'bulk_product_submission_id',
        'comcode',
        'status',
        'remote_product_id',
        'request_payload',
        'response_payload',
        'error_message',
        'attempts',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(BulkProductSubmission::class, 'bulk_product_submission_id');
    }
}
