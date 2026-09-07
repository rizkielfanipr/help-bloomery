<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TechnicianMaintenanceChecklist extends Model
{
    use HasFactory;

    protected $fillable = ['section_code', 'section_name', 'question', 'check_procedure', 'points', 'is_critical', 'requires_photo', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'is_critical' => 'boolean', 'requires_photo' => 'boolean', 'is_active' => 'boolean'];
    }

    public function maintenanceItems(): HasMany
    {
        return $this->hasMany(TechnicianMaintenanceItem::class, 'checklist_id');
    }
}
