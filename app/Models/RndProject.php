<?php

namespace App\Models;

use App\Enums\RndProjectType;
use Database\Factories\RndProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RndProject extends Model
{
    /** @use HasFactory<RndProjectFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = ['project_type' => 'release_product'];

    protected $fillable = [
        'name',
        'description',
        'project_type',
        'partnership_event_name',
        'start_date',
        'end_date',
        'forecast_percentage',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'forecast_percentage' => 'decimal:2',
            'project_type' => RndProjectType::class,
        ];
    }

    public function boms(): HasMany
    {
        return $this->hasMany(RndProjectBom::class)->latest('updated_at');
    }

    public function products(): HasMany
    {
        return $this->hasMany(RndProjectProduct::class)->latest('updated_at');
    }

    public function partnershipMenus(): HasMany
    {
        return $this->hasMany(RndProjectPartnershipMenu::class)->orderBy('sort_order')->orderBy('id');
    }

    public function partnershipBranches(): BelongsToMany
    {
        return $this->belongsToMany(
            Branch::class,
            'rnd_project_partnership_branches',
            'rnd_project_id',
            'branch_id',
        )->withTimestamps();
    }

    public function partnershipEquipment(): HasMany
    {
        return $this->hasMany(RndProjectPartnershipEquipment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function premixAssemblies(): HasMany
    {
        return $this->hasMany(RndProjectPremixAssembly::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RndProjectDocument::class)->latest();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(RndProjectTask::class)->latest('due_date');
    }

    public function taskTemplateApplications(): HasMany
    {
        return $this->hasMany(RndProjectTaskTemplateApplication::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
