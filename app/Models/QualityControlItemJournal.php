<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityControlItemJournal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['journal_date' => 'date', 'request_payload' => 'array', 'response_payload' => 'array', 'submitted_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(QualityControlItemJournalDetail::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(QualityControlItemJournalAttachment::class);
    }
}
