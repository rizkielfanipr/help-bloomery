<?php

namespace App\Models;

use Database\Factories\RndProductSalesProjectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RndProductSalesProjection extends Model
{
    /** @use HasFactory<RndProductSalesProjectionFactory> */
    use HasFactory;

    public const CHANNELS = [
        'all' => 'All Channel',
        'offline' => 'Offline',
        'online' => 'Online',
    ];

    protected $fillable = [
        'rnd_project_product_id',
        'sales_region_id',
        'projection_month',
        'channel',
        'target_quantity',
        'target_revenue',
        'target_outlets',
        'notes',
        'created_by',
    ];

    protected $attributes = [
        'channel' => 'all',
    ];

    protected function casts(): array
    {
        return [
            'projection_month' => 'date',
            'target_quantity' => 'decimal:2',
            'target_revenue' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RndProjectProduct::class, 'rnd_project_product_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(SalesRegion::class, 'sales_region_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetBranches(): BelongsToMany
    {
        return $this->belongsToMany(
            Branch::class,
            'rnd_product_sales_projection_branch_targets',
            'rnd_product_sales_projection_id',
            'branch_id',
        )->withPivot('target_quantity')->withTimestamps();
    }
}
