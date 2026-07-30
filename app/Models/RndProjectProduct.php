<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RndProjectProduct extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'development' => 'Development',
        'trial' => 'Trial',
        'ready' => 'Ready',
        'released' => 'Released',
        'cancelled' => 'Cancelled',
    ];

    public const BOM_USAGE_TYPES = [
        'main' => 'Main',
        'component' => 'Component',
        'packaging' => 'Packaging',
        'supporting' => 'Supporting',
    ];

    protected $fillable = [
        'rnd_project_id',
        'name',
        'product_code',
        'description',
        'image_path',
        'offline_price',
        'online_price',
        'release_date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'offline_price' => 'decimal:2',
            'online_price' => 'decimal:2',
            'release_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'rnd_project_id');
    }

    public function boms(): BelongsToMany
    {
        return $this->belongsToMany(
            RndProjectBom::class,
            'rnd_project_product_boms',
            'rnd_project_product_id',
            'rnd_project_bom_id',
        )->withPivot(['usage_type', 'notes', 'parent_rnd_project_bom_id'])->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function marketingMaterials(): HasMany
    {
        return $this->hasMany(RndProjectMarketingMaterial::class)->latest();
    }

    public function esbMaterials(): HasMany
    {
        return $this->hasMany(RndProductEsbMaterial::class)->latest();
    }

    public function regionalPrices(): HasMany
    {
        return $this->hasMany(RndProductRegionalPrice::class)->latest('effective_from');
    }

    public function currentRegionalPrices(): HasMany
    {
        return $this->hasMany(RndProductRegionalPrice::class)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', today())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))
            ->latest('effective_from');
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        try {
            return Storage::disk('b2')->temporaryUrl($this->image_path, now()->addHour());
        } catch (Throwable) {
            return Storage::disk('b2')->url($this->image_path);
        }
    }
}
