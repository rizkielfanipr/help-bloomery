<?php

namespace App\Models;

use App\Enums\SalesReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReportSettings extends Model
{
    use HasFactory;

    protected $fillable = ['auto_reject_enabled', 'auto_reject_after_days', 'auto_reject_reason', 'effective_from'];

    protected $attributes = ['auto_reject_enabled' => false, 'auto_reject_after_days' => 3];

    protected function casts(): array
    {
        return ['auto_reject_enabled' => 'boolean', 'auto_reject_after_days' => 'integer', 'effective_from' => 'date'];
    }

    public static function instance(): static
    {
        return static::firstOrCreate(['id' => 1], [
            'auto_reject_enabled' => false,
            'auto_reject_after_days' => 3,
            'auto_reject_reason' => 'Tidak ada approval Supervisor dalam :days hari setelah seluruh shift dikirim.',
            'effective_from' => '2026-09-01',
        ]);
    }

    public function formattedAutoRejectReason(): string
    {
        return str_replace(':days', (string) $this->auto_reject_after_days, $this->auto_reject_reason);
    }

    public function eligibleReports(): Builder
    {
        return SalesReport::query()->where('status', SalesReportStatus::PendingSupervisor->value)
            ->whereDate('report_date', '>=', $this->effective_from->toDateString())
            ->whereNotNull('submitted_at')->where('submitted_at', '<=', now()->subDays($this->auto_reject_after_days));
    }
}
