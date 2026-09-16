<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityControlItemJournalAttachment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['uploaded_to_esb_at' => 'datetime'];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(QualityControlItemJournal::class, 'quality_control_item_journal_id');
    }
}
