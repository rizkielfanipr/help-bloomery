<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchEsbCode extends Model
{
    protected $fillable = [
        'branch_id',
        'esb_branch_id',
        'esb_branch_code',
        'esb_comcode',
        'label',
        'is_active',
        'esb_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'esb_branch_id' => 'integer',
            'is_active' => 'boolean',
            'esb_synced_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getEsbTokenAttribute(): string
    {
        return config('esb.tokens.'.$this->esb_comcode, '');
    }
}
