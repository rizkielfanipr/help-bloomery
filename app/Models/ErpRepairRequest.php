<?php

namespace App\Models;

use App\Enums\ItRequestStatus;
use Database\Factories\ErpRepairRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ErpRepairRequest extends Model
{
    /** @use HasFactory<ErpRepairRequestFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'requester_id',
        'ticket_number',
        'branch_id',
        'erp_module_id',
        'request_type_id',
        'keterangan',
        'attachments',
        'status',
        'priority',
        'it_notes',
        'closed_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'status' => ItRequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ErpModule::class, 'erp_module_id');
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(ItRequestType::class, 'request_type_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ItRequestActivity::class)->latest();
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if ($request->ticket_number) {
                return;
            }

            do {
                $ticket = 'IT-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (self::withTrashed()->where('ticket_number', $ticket)->exists());

            $request->ticket_number = $ticket;
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
