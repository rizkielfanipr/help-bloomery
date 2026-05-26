<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    protected $fillable = [
        'service_template_id',
        'technician_id',
        'scheduled_by',
        'title',
        'description',
        'status',
        'scheduled_at',
        'before_photo',
        'before_notes',
        'after_photo',
        'after_notes',
        'started_at',
        'completed_at',
        'warranty_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceRequestStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'warranty_expires_at' => 'datetime',
        ];
    }

    public function serviceTemplate(): BelongsTo
    {
        return $this->belongsTo(ServiceTemplate::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function checkAndAutoComplete(): void
    {
        if (
            $this->status === ServiceRequestStatus::Warranty
            && $this->warranty_expires_at
            && $this->warranty_expires_at->isPast()
        ) {
            $this->update(['status' => ServiceRequestStatus::Completed]);
        }
    }
}
