<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReportApproval extends Model
{
    protected $fillable = [
        'sales_report_id', 'stage', 'action', 'actor_id', 'notes',
        'revision_number', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
