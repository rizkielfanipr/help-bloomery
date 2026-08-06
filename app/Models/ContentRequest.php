<?php

namespace App\Models;

use App\Enums\ContentRequestStatus;
use Database\Factories\ContentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ContentRequest extends Model
{
    /** @use HasFactory<ContentRequestFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'requester_id',
        'branch_id',
        'judul_konten',
        'jenis_konten',
        'platform_tujuan',
        'tujuan_konten',
        'link_contoh_konten',
        'attachments',
        'status',
        'admin_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'status' => ContentRequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if ($request->code) {
                return;
            }

            do {
                $code = 'CR-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (self::withTrashed()->where('code', $code)->exists());

            $request->code = $code;
        });

        static::created(function (ContentRequest $request): void {
            $request->statusHistories()->create([
                'from_status' => null,
                'to_status' => $request->status->value,
                'changed_by' => auth()->id() ?: $request->requester_id,
            ]);
        });

        static::updated(function (ContentRequest $request): void {
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
        return $this->hasMany(ContentRequestStatusHistory::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
