<?php

namespace App\Models;

use App\Enums\BriefingTaskKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefingItem extends Model
{
    protected $fillable = [
        'briefing_record_id', 'task_key', 'photo_path',
        'notes', 'is_completed', 'completed_at',
        'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'task_key' => BriefingTaskKey::class,
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(BriefingRecord::class, 'briefing_record_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
