<?php

namespace App\Models;

use Database\Factories\BulkProductSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkProductSubmission extends Model
{
    /** @use HasFactory<BulkProductSubmissionFactory> */
    use HasFactory;

    public const COMCODES = ['BLSS', 'BLO6', 'BLO7', 'BLO10', 'BLMN'];

    protected $fillable = [
        'operation',
        'product_code',
        'product_name',
        'target_comcodes',
        'remote_product_ids',
        'payload',
        'status',
        'created_by',
        'submitted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_comcodes' => 'array',
            'remote_product_ids' => 'array',
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BulkProductSubmissionItem::class);
    }
}
