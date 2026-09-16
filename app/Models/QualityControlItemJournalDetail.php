<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityControlItemJournalDetail extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'hpp' => 'decimal:4'];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(QualityControlItemJournal::class, 'quality_control_item_journal_id');
    }
}
