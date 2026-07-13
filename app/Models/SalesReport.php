<?php

namespace App\Models;

use Database\Factories\SalesReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReport extends Model
{
    /** @use HasFactory<SalesReportFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'submitted_by',
        'report_date',
        'submitted_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SalesReportEntry::class);
    }

    public function getTotalSystemAttribute(): float
    {
        return (float) $this->entries->sum('sales_system_amount');
    }

    public function getTotalStoreAttribute(): float
    {
        return (float) $this->entries->sum('sales_store_amount');
    }
}
