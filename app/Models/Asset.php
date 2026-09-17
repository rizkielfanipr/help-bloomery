<?php

namespace App\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    protected $fillable = ['branch_id', 'asset_number', 'name', 'category', 'brand', 'model', 'serial_number', 'qr_token', 'qr_svg_path', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function completedRepairs(): HasManyThrough
    {
        return $this->hasManyThrough(ServiceRequestRepair::class, ServiceRequest::class)->whereNotNull('service_request_repairs.completed_at');
    }

    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'Inactive';
        }

        return $this->serviceRequests()->whereNotIn('status', ['warranty', 'completed'])->exists() ? 'Maintenance' : 'Active';
    }

    public function qrDataUri(): ?string
    {
        if (! $this->qr_svg_path || ! Storage::disk('b2')->exists($this->qr_svg_path)) {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode(Storage::disk('b2')->get($this->qr_svg_path));
    }
}
