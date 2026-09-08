<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSopAssignment extends Model
{
    protected $fillable = ['store_sop_id', 'branch_id', 'user_id', 'assigned_at', 'opened_at', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'opened_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    public function sop(): BelongsTo
    {
        return $this->belongsTo(StoreSop::class, 'store_sop_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
