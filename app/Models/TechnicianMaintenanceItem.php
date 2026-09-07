<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TechnicianMaintenanceItem extends Model
{
    protected $fillable = ['technician_maintenance_id', 'checklist_id', 'question', 'check_procedure', 'requires_photo', 'result', 'notes', 'photo_paths', 'sort_order'];

    protected function casts(): array
    {
        return ['photo_paths' => 'array', 'requires_photo' => 'boolean'];
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(TechnicianMaintenance::class, 'technician_maintenance_id');
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(TechnicianMaintenanceChecklist::class, 'checklist_id');
    }

    /**
     * @return array<int, string>
     */
    public function photoUrls(): array
    {
        return collect($this->photo_paths ?? [])
            ->map(function (string $path): string {
                try {
                    return Storage::disk('b2')->temporaryUrl($path, now()->addHour());
                } catch (Throwable) {
                    return Storage::disk('b2')->url($path);
                }
            })
            ->all();
    }
}
