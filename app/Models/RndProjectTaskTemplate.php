<?php

namespace App\Models;

use Database\Factories\RndProjectTaskTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Reusable checkpoint blueprint for R&D Project Tasks (docs/rnd-project-checkpoint-calendar-prd.md
 * §16.1). Applying it creates independent `RndProjectTask` snapshots, so editing or deactivating a
 * template never changes Tasks created earlier. Templates that were applied are deactivated, not
 * deleted (§12.9).
 */
class RndProjectTaskTemplate extends Model
{
    /** @use HasFactory<RndProjectTaskTemplateFactory> */
    use HasFactory, LogsActivity;

    /**
     * Upper bound of checkpoints per template and per apply batch (§12.3) — it also caps the
     * notification fan-out and the preview payload.
     */
    public const MAX_CHECKPOINTS = 30;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(RndProjectTaskTemplateCheckpoint::class)->orderBy('sort_order')->orderBy('id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(RndProjectTaskTemplateApplication::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param  Builder<RndProjectTaskTemplate>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Reuses a `withCount('applications')` value when present so listing pages stay query-free.
     */
    public function hasBeenApplied(): bool
    {
        if (array_key_exists('applications_count', $this->attributes)) {
            return $this->applications_count > 0;
        }

        return $this->applications()->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
