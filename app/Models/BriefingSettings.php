<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BriefingSettings extends Model
{
    protected $fillable = [
        'auto_reject_after_days',
        'auto_reject_reason',
        'deadline_reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'auto_reject_after_days' => 'integer',
        ];
    }

    /**
     * Get or create the single settings instance.
     */
    public static function instance(): static
    {
        return static::firstOrCreate(['id' => 1], [
            'auto_reject_after_days' => 3,
            'auto_reject_reason' => 'Tidak ada approval dalam :days hari setelah poin diselesaikan.',
            'deadline_reject_reason' => 'Tidak ada input sebelum deadline.',
        ]);
    }

    public function formattedAutoRejectReason(int $days): string
    {
        return str_replace(':days', (string) $days, $this->auto_reject_reason);
    }
}
