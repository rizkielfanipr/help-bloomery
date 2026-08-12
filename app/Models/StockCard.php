<?php

namespace App\Models;

use App\Enums\StockCardStatus;
use Database\Factories\StockCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCard extends Model
{
    /** @use HasFactory<StockCardFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'submitted_by',
        'report_date',
        'flag_unit',
        'submitted_at',
        'status',
        'supervisor_reviewed_by',
        'supervisor_reviewed_at',
        'supervisor_note',
        'finance_reviewed_by',
        'finance_reviewed_at',
        'finance_note',
        'system_fetched_at',
        'revision_number',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'submitted_at' => 'datetime',
            'supervisor_reviewed_at' => 'datetime',
            'finance_reviewed_at' => 'datetime',
            'system_fetched_at' => 'datetime',
            'status' => StockCardStatus::class,
            'revision_number' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function supervisorReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_reviewed_by');
    }

    public function financeReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_reviewed_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StockCardEntry::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(StockCardEmployee::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(StockCardApproval::class)->latest();
    }

    /** Entries where the working qty differs from ESB's system qty. */
    public function getTotalVarianceItemsAttribute(): int
    {
        return $this->entries->filter(fn (StockCardEntry $e) => $e->actual_qty !== null
            && $e->system_qty !== null
            && (float) $e->actual_qty !== (float) $e->system_qty
        )->count();
    }
}
