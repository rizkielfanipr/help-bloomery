<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'technician_id',
        'scheduled_by',
        'scheduled_date',
        'requestor_notes',
        'attachments',
        'status',
        'warranty_expires_at',
        'warranty_claim_notes',
        'warranty_claim_attachments',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceRequestStatus::class,
            'scheduled_date' => 'date',
            'attachments' => 'array',
            'warranty_claim_attachments' => 'array',
            'warranty_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if ($request->code) {
                return;
            }

            do {
                $code = 'SR-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (self::where('code', $code)->exists());

            $request->code = $code;
        });
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    /** @return HasMany<ServiceRequestRepair, $this> */
    public function repairs(): HasMany
    {
        return $this->hasMany(ServiceRequestRepair::class)->orderBy('cycle');
    }

    /** The current open (in-progress) repair, i.e. not yet completed. */
    public function activeRepair(): HasOne
    {
        return $this->hasOne(ServiceRequestRepair::class)->whereNull('completed_at')->latestOfMany('cycle');
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
