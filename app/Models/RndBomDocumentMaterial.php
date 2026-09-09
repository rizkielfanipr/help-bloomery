<?php

namespace App\Models;

use Database\Factories\RndBomDocumentMaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndBomDocumentMaterial extends Model
{
    /** @use HasFactory<RndBomDocumentMaterialFactory> */
    use HasFactory;

    protected $fillable = ['rnd_project_bom_id', 'name', 'quantity', 'unit', 'notes', 'sort_order'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'sort_order' => 'integer'];
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(RndProjectBom::class, 'rnd_project_bom_id');
    }
}
