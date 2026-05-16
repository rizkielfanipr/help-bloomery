<?php

namespace App\Models;

use Database\Factories\MaintenanceReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class MaintenanceReport extends Model
{
    /** @use HasFactory<MaintenanceReportFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'maintenance_schedule_id',
        'technician_id',
        'kondisi_sebelum',
        'kondisi_sesudah',
        'tindakan_perbaikan',
        'before_photos',
        'after_photos',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'before_photos' => 'array',
            'after_photos' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function maintenanceSchedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
