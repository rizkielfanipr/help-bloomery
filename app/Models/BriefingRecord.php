<?php

namespace App\Models;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingTaskKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BriefingRecord extends Model
{
    protected $fillable = ['user_id', 'period', 'record_date', 'submitted_at'];

    protected $casts = [
        'period' => BriefingPeriod::class,
        'record_date' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BriefingItem::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isFullyComplete(): bool
    {
        return $this->items->every(fn (BriefingItem $item) => $item->is_completed);
    }

    public function completedCount(): int
    {
        return $this->items->filter(fn (BriefingItem $item) => $item->is_completed)->count();
    }

    public function createDefaultItems(): void
    {
        foreach (BriefingTaskKey::forPeriod($this->period) as $task) {
            $this->items()->firstOrCreate(['task_key' => $task->value]);
        }
    }
}
