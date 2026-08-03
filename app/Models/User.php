<?php

namespace App\Models;

use App\Services\PermissionRegistry;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'password', 'branch_id', 'phone', 'bank_name', 'bank_account_number', 'avatar', 'is_active', 'casual_position_id', 'access_all_branches'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'access_all_branches' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'helpdesk' => $this->can('access backoffice'),
            'casual' => $this->hasAnyConfiguredPermission(
                ['Akses Employee App'],
                app(PermissionRegistry::class)->groups(),
            ),
            'driver' => $this->can('access employee app driver'),
            'technician' => $this->can('access employee app technician'),
            'admin' => $this->can('access backoffice')
                && $this->hasAnyConfiguredPermission(
                    ['Management Access'],
                    app(PermissionRegistry::class)->groups(),
                ),
            default => false,
        };
    }

    /**
     * @param  array<int, string>  $groups
     * @param  array<string, array<string, array<int, string>>>  $permissionGroups
     */
    private function hasAnyConfiguredPermission(array $groups, array $permissionGroups): bool
    {
        foreach ($groups as $group) {
            foreach ($permissionGroups[$group] ?? [] as $permissions) {
                foreach ($permissions as $permission) {
                    if ($this->can($permission)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supervisedBranches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branches')->withPivot('is_primary');
    }

    public function accessibleBranches(): BelongsToMany
    {
        return $this->supervisedBranches();
    }

    public function primaryBranchId(): ?int
    {
        $pivotPrimary = $this->accessibleBranches()
            ->wherePivot('is_primary', true)
            ->value('branches.id');

        return $pivotPrimary ? (int) $pivotPrimary : ($this->branch_id ? (int) $this->branch_id : null);
    }

    public function accessibleBranchIds(): Collection
    {
        if ($this->canAccessAllBranches()) {
            return Branch::query()->pluck('id')->map(fn ($id): int => (int) $id);
        }

        $ids = $this->accessibleBranches()->pluck('branches.id');
        if ($this->branch_id) {
            $ids->push($this->branch_id);
        }

        return $ids->map(fn ($id): int => (int) $id)->unique()->values();
    }

    public function canAccessBranch(?int $branchId): bool
    {
        if (! $branchId) {
            return false;
        }

        return $this->canAccessAllBranches()
            || $this->accessibleBranchIds()->contains($branchId);
    }

    public function canAccessAllBranches(): bool
    {
        return $this->access_all_branches || $this->hasRole('SUPERADMIN');
    }

    public function syncBranchAccess(array $branchIds, ?int $primaryBranchId): void
    {
        $branchIds = collect($branchIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($primaryBranchId && ! $branchIds->contains($primaryBranchId)) {
            $branchIds->push($primaryBranchId);
        }

        if (! $primaryBranchId && $branchIds->count() === 1) {
            $primaryBranchId = $branchIds->first();
        }

        DB::transaction(function () use ($branchIds, $primaryBranchId): void {
            $this->accessibleBranches()->sync($branchIds->mapWithKeys(
                fn (int $branchId): array => [$branchId => ['is_primary' => $branchId === $primaryBranchId]],
            )->all());
            $this->forceFill(['branch_id' => $primaryBranchId])->saveQuietly();
        });
    }

    public function casualPosition(): BelongsTo
    {
        return $this->belongsTo(CasualPosition::class, 'casual_position_id');
    }

    public function casualPositionRegistration(): HasOne
    {
        return $this->hasOne(CasualPositionRegistration::class)
            ->whereHas('opening', fn ($q) => $q->where('work_date', '>=', today())->orderBy('work_date'))
            ->ofMany(['id' => 'min'], fn ($q) => $q->whereHas(
                'opening', fn ($q) => $q->where('work_date', '>=', today())
            ));
    }

    public function casualPositionRegistrations(): HasMany
    {
        return $this->hasMany(CasualPositionRegistration::class);
    }

    public function upcomingRegistrations(): HasMany
    {
        return $this->hasMany(CasualPositionRegistration::class)
            ->whereHas('opening', fn ($q) => $q->where('work_date', '>=', today()));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
