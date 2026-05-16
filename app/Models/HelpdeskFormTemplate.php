<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskFormTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'color',
        'is_active',
        'statuses',
        'roles',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'statuses' => 'array',
            'roles' => 'array',
        ];
    }

    public function isAccessibleByUser(User $user): bool
    {
        if (empty($this->roles)) {
            return true;
        }

        return $user->hasAnyRole($this->roles);
    }

    /** @return array<int, array{label: string, value: string, color: string}> */
    public function getStatusOptions(): array
    {
        return collect($this->statuses ?? [])
            ->pluck('label', 'value')
            ->toArray();
    }

    public function fields(): HasMany
    {
        return $this->hasMany(HelpdeskFormField::class)->orderBy('sort_order');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(HelpdeskRequest::class);
    }
}
