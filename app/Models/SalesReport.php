<?php

namespace App\Models;

use App\Enums\SalesReportStatus;
use Database\Factories\SalesReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class SalesReport extends Model
{
    /** @use HasFactory<SalesReportFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'report_date',
        'submitted_at',
        'status',
        'supervisor_reviewed_by',
        'supervisor_reviewed_at',
        'supervisor_note',
        'finance_reviewed_by',
        'finance_reviewed_at',
        'finance_note',
        'revision_number',
    ];

    protected $casts = [
        'report_date' => 'date',
        'submitted_at' => 'datetime',
        'status' => SalesReportStatus::class,
        'supervisor_reviewed_at' => 'datetime',
        'finance_reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (SalesReport $salesReport): void {
            $paths = $salesReport->compliments()
                ->get(['attachment_paths'])
                ->flatMap(fn (SalesReportCompliment $compliment): array => $compliment->attachment_paths ?? [])
                ->all();

            if ($paths !== []) {
                Storage::disk('b2')->delete($paths);
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(SalesReportEmployee::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SalesReportEntry::class);
    }

    public function shiftSubmissions(): HasMany
    {
        return $this->hasMany(SalesReportShiftSubmission::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(SalesReportReconciliation::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(SalesReportApproval::class)->latest();
    }

    public function esbTransactions(): HasMany
    {
        return $this->hasMany(SalesReportEsbTransaction::class);
    }

    public function basketSizeRecords(): HasMany
    {
        return $this->hasMany(BasketSizeRecord::class);
    }

    public function compliments(): HasMany
    {
        return $this->hasMany(SalesReportCompliment::class);
    }

    public function supervisorReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_reviewed_by');
    }

    public function financeReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_reviewed_by');
    }

    public function isShiftSubmitted(int $shiftNumber): bool
    {
        return $this->shiftSubmissions->contains(fn (SalesReportShiftSubmission $s): bool => $s->shift_number === $shiftNumber);
    }

    /** @param int|array<int, int> $shifts */
    public function allShiftsSubmitted(int|array $shifts): bool
    {
        $shiftNumbers = is_int($shifts) ? range(1, $shifts) : $shifts;

        foreach ($shiftNumbers as $shift) {
            if (! $this->isShiftSubmitted($shift)) {
                return false;
            }
        }

        return true;
    }

    public function getTotalStoreAttribute(): float
    {
        return (float) $this->entries->sum('sales_store_amount');
    }

    public function getTotalSystemAttribute(): float
    {
        return (float) $this->reconciliations->sum('system_amount');
    }

    public function getTotalSettlementAttribute(): float
    {
        return (float) $this->reconciliations->sum('settlement_amount');
    }
}
