<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use Database\Factories\MaintenanceScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaintenanceSchedule extends Model
{
    /** @use HasFactory<MaintenanceScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'erp_repair_request_id',
        'technician_id',
        'scheduled_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'status' => MaintenanceStatus::class,
        ];
    }

    public function erpRepairRequest(): BelongsTo
    {
        return $this->belongsTo(ErpRepairRequest::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(MaintenanceReport::class);
    }
}
