<?php

namespace App\Models;

use Database\Factories\RndProjectPartnershipEquipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndProjectPartnershipEquipment extends Model
{
    /** @use HasFactory<RndProjectPartnershipEquipmentFactory> */
    use HasFactory;

    protected $table = 'rnd_project_partnership_equipment';

    protected $fillable = [
        'rnd_project_id', 'product_detail_id', 'product_code', 'product_name', 'uom_name',
        'quantity', 'notes', 'sort_order', 'created_by',
    ];

    protected function casts(): array
    {
        return ['product_detail_id' => 'integer', 'quantity' => 'decimal:4', 'sort_order' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'rnd_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
