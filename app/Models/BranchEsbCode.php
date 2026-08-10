<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchEsbCode extends Model
{
    protected $fillable = [
        'branch_id',
        'esb_branch_code',
        'esb_comcode',
        'label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
