<?php

namespace App\Models;

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
            'admin' => $this->hasRole('SUPERADMIN'),
            'helpdesk' => $this->hasAnyRole(['SUPERADMIN', 'HRD_STAFF', 'SUPERVISOR_STORE', 'FINANCE_STAFF', 'STORE_STAFF', 'DRIVER', 'TECHNICIAN', 'IT_STAFF', 'RND_STAFF']),
            'driver' => $this->hasAnyRole(['SUPERADMIN', 'DRIVER']),
            'technician' => $this->hasAnyRole(['SUPERADMIN', 'TECHNICIAN']),
            'casual' => $this->hasAnyRole(['SUPERADMIN', 'CASUAL_STAFF', 'HRD_STAFF', 'STORE_STAFF', 'DRIVER', 'TECHNICIAN', 'SUPERVISOR_STORE']),
            default => false,
        };
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
        if ($this->access_all_branches) {
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

        return $this->access_all_branches
            || $this->hasRole('SUPERADMIN')
            || $this->accessibleBranchIds()->contains($branchId);
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
