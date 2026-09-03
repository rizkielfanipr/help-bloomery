<?php

namespace App\Models;

use Database\Factories\ComplimentTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplimentType extends Model
{
    /** @use HasFactory<ComplimentTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function salesReportCompliments(): HasMany
    {
        return $this->hasMany(SalesReportCompliment::class);
    }
}
