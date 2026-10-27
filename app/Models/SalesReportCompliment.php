<?php

namespace App\Models;

use App\Enums\SalesReportDataSource;
use App\Services\SalesReport\SalesReportEvidenceStore;
use Database\Factories\SalesReportComplimentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReportCompliment extends Model
{
    /** @use HasFactory<SalesReportComplimentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (SalesReportCompliment $compliment): void {
            app(SalesReportEvidenceStore::class)->delete($compliment->attachment_paths ?? []);
        });
    }

    protected $fillable = [
        'sales_report_id',
        'shift_number',
        'sales_number',
        'sales_number_source',
        'source_comcode',
        'source_branch_code',
        'sales_transaction_at',
        'compliment_type_id',
        'compliment_type_name',
        'attachment_paths',
        'notes',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'shift_number' => 'integer',
            'sales_number_source' => SalesReportDataSource::class,
            'sales_transaction_at' => 'datetime',
            'attachment_paths' => 'array',
        ];
    }

    /**
     * Rows saved before the Sales Number requirement have no source; they are presented as legacy
     * instead of being backfilled (docs/products/finance/sales-report-compliment-void-remediation/prd.md §18.2).
     */
    public function effectiveSalesNumberSource(): SalesReportDataSource
    {
        return $this->sales_number_source ?? SalesReportDataSource::Legacy;
    }

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(SalesReport::class);
    }

    public function complimentType(): BelongsTo
    {
        return $this->belongsTo(ComplimentType::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
