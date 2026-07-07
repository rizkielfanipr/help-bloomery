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
        'modal_shift_1',
        'modal_shift_2',
        'shift_1_submitted_at',
        'shift_2_submitted_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'modal_shift_1' => 'decimal:2',
        'modal_shift_2' => 'decimal:2',
        'shift_1_submitted_at' => 'datetime',
        'shift_2_submitted_at' => 'datetime',
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

    public function getTotalShift1Attribute(): float
    {
        return (float) $this->entries->sum('shift_1_amount');
    }

    public function getTotalShift2Attribute(): float
    {
        return (float) $this->entries->sum('shift_2_amount');
    }
}
