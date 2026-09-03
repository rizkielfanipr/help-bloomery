<?php

namespace App\Models;

use Database\Factories\SalesReportComplimentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SalesReportCompliment extends Model
{
    /** @use HasFactory<SalesReportComplimentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (SalesReportCompliment $compliment): void {
            $paths = $compliment->attachment_paths ?? [];

            if ($paths !== []) {
                Storage::disk('b2')->delete($paths);
            }
        });
    }

    protected $fillable = [
        'sales_report_id',
        'shift_number',
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
            'attachment_paths' => 'array',
        ];
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
