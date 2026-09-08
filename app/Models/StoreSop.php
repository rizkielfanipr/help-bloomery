<?php

namespace App\Models;

use Database\Factories\StoreSopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StoreSop extends Model
{
    /** @use HasFactory<StoreSopFactory> */
    use HasFactory;

    protected $fillable = ['code', 'title', 'version', 'category', 'summary', 'file_path', 'original_name', 'effective_date', 'status', 'created_by', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return ['effective_date' => 'date', 'published_at' => 'datetime'];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'store_sop_branch')->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StoreSopAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function downloadUrl(): string
    {
        try {
            $name = str_replace(['"', "\r", "\n"], '', basename($this->original_name ?: $this->file_path));

            return Storage::disk('b2')->temporaryUrl($this->file_path, now()->addMinutes(15), [
                'ResponseContentDisposition' => 'attachment; filename="'.$name.'"',
            ]);
        } catch (Throwable) {
            return Storage::disk('b2')->url($this->file_path);
        }
    }
}
