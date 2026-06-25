<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestRepair extends Model
{
    protected $fillable = [
        'service_request_id',
        'technician_id',
        'cycle',
        'before_photo',
        'before_notes',
        'after_photo',
        'after_notes',
        'started_at',
        'completed_at',
        'warranty_expires_at',
        'warranty_claim_notes',
        'warranty_claim_attachments',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'warranty_expires_at' => 'datetime',
            'warranty_claim_attachments' => 'array',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function getCycleLabelAttribute(): string
    {
        return $this->cycle === 1
            ? 'Perbaikan Awal'
            : "Perbaikan ke-{$this->cycle} — Klaim Garansi";
    }

    public function getIsCompleteAttribute(): bool
    {
        return $this->completed_at !== null;
    }
}
