<?php

namespace App\Models;

use App\Enums\SalesReportDataSource;
use App\Services\SalesReport\SalesReportEvidenceStore;
use Database\Factories\SalesReportVoidFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One voided transaction reported on a Sales Report shift, with two separate receipt photos
 * (docs/sales-report-compliment-void-remediation-prd.md §10).
 */
class SalesReportVoid extends Model
{
    /** @use HasFactory<SalesReportVoidFactory> */
    use HasFactory;

    protected $fillable = [
        'sales_report_id',
        'shift_number',
        'sales_number',
        'sales_number_source',
        'source_comcode',
        'source_branch_code',
        'sales_transaction_at',
        'transaction_amount',
        'payment_method_id',
        'payment_method_name',
        'payment_method_source',
        'reason',
        'original_receipt_path',
        'voided_receipt_path',
        'submitted_by',
    ];

    protected static function booted(): void
    {
        static::deleting(function (SalesReportVoid $void): void {
            app(SalesReportEvidenceStore::class)->delete([$void->original_receipt_path, $void->voided_receipt_path]);
        });
    }

    protected function casts(): array
    {
        return [
            'shift_number' => 'integer',
            'sales_number_source' => SalesReportDataSource::class,
            'payment_method_source' => SalesReportDataSource::class,
            'sales_transaction_at' => 'datetime',
            'transaction_amount' => 'decimal:2',
        ];
    }

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
