<?php

namespace App\Models;

use App\Enums\DesignRequestStatus;
use Database\Factories\DesignRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class DesignRequest extends Model
{
    /** @use HasFactory<DesignRequestFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'requester_id',
        'branch_id',
        'design_category_id',
        'judul_permintaan',
        'ringkasan_brief',
        'attachments',
        'status',
        'admin_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'status' => DesignRequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (DesignRequest $request): void {
            $request->statusHistories()->create([
                'from_status' => null,
                'to_status' => $request->status->value,
                'changed_by' => auth()->id() ?: $request->requester_id,
            ]);
        });

        static::updated(function (DesignRequest $request): void {
            if (! $request->wasChanged('status')) {
                return;
            }

            $request->statusHistories()->create([
                'from_status' => $request->getRawOriginal('status'),
                'to_status' => $request->status->value,
                'changed_by' => auth()->id(),
                'notes' => $request->admin_notes,
            ]);
        });
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(DesignRequestStatusHistory::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DesignCategory::class, 'design_category_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
