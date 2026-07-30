<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RndBomInstruction extends Model
{
    protected $fillable = [
        'rnd_project_id',
        'rnd_project_product_id',
        'esb_bom_id',
        'content_html',
        'image_paths',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'esb_bom_id' => 'integer',
            'image_paths' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'rnd_project_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RndProjectProduct::class, 'rnd_project_product_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function imageUrls(): array
    {
        return collect($this->image_paths ?? [])->map(function (string $path): array {
            try {
                $url = Storage::disk('b2')->temporaryUrl($path, now()->addHour());
            } catch (Throwable) {
                $url = Storage::disk('b2')->url($path);
            }

            return ['path' => $path, 'url' => $url];
        })->all();
    }
}
