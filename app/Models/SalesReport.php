<?php

namespace App\Models;

use App\Enums\SalesReportStatus;
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
        'shift_number',
        'shift_started_at',
        'shift_ended_at',
        'submitted_at',
        'status',
        'supervisor_reviewed_by',
        'supervisor_reviewed_at',
        'supervisor_note',
        'finance_reviewed_by',
        'finance_reviewed_at',
        'finance_note',
        'rejection_reason',
        'revision_number',
    ];

    protected $casts = [
        'report_date' => 'date',
        'shift_number' => 'integer',
        'shift_started_at' => 'datetime',
        'shift_ended_at' => 'datetime',
        'submitted_at' => 'datetime',
        'status' => SalesReportStatus::class,
        'supervisor_reviewed_at' => 'datetime',
        'finance_reviewed_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(SalesReportEmployee::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SalesReportEntry::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(SalesReportApproval::class)->latest();
    }

    public function esbTransactions(): HasMany
    {
        return $this->hasMany(SalesReportEsbTransaction::class);
    }

    public function supervisorReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_reviewed_by');
    }

    public function financeReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_reviewed_by');
    }

    public function getTotalSystemAttribute(): float
    {
        return (float) $this->entries->sum('sales_system_amount');
    }

    public function getTotalStoreAttribute(): float
    {
        return (float) $this->entries->sum('sales_store_amount');
    }

    public function getTotalSettlementAttribute(): float
    {
        return (float) $this->entries->sum('settlement_amount');
    }
}
