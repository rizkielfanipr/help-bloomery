<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianMaintenanceItem extends Model
{
    protected $fillable = ['technician_maintenance_id', 'checklist_id', 'section_code', 'section_name', 'question', 'check_procedure', 'maximum_points', 'earned_points', 'is_critical', 'requires_photo', 'result', 'notes', 'photo_paths', 'sort_order'];

    protected function casts(): array
    {
        return ['photo_paths' => 'array', 'is_critical' => 'boolean', 'requires_photo' => 'boolean'];
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(TechnicianMaintenance::class, 'technician_maintenance_id');
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(TechnicianMaintenanceChecklist::class, 'checklist_id');
    }
}
