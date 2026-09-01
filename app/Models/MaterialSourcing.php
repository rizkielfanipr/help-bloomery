<?php

namespace App\Models;

use Database\Factories\MaterialSourcingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MaterialSourcing extends Model
{
    /** @use HasFactory<MaterialSourcingFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_product_esb_material_id',
        'supplier_name',
        'price',
        'moq',
        'lead_time_days',
        'contact_name',
        'contact_phone',
        'notes',
        'attachment_path',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'lead_time_days' => 'integer',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(RndProductEsbMaterial::class, 'rnd_product_esb_material_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function attachmentUrl(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        try {
            return Storage::disk('b2')->temporaryUrl($this->attachment_path, now()->addHour());
        } catch (Throwable) {
            return Storage::disk('b2')->url($this->attachment_path);
        }
    }
}
