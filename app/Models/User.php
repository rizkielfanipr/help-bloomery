<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'employee_id', 'department_id', 'phone', 'avatar', 'is_active', 'casual_position_id', 'casual_shift_id'])]
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
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->hasRole('super_admin'),
            'helpdesk' => $this->hasAnyRole(['super_admin', 'helpdesk_manager', 'helpdesk_staff']),
            'driver' => $this->hasAnyRole(['super_admin', 'driver']),
            'technician' => $this->hasAnyRole(['super_admin', 'technician']),
            'casual' => $this->hasAnyRole(['super_admin', 'hr_staff', 'casual_staff']),
            default => false,
        };
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function casualPosition(): BelongsTo
    {
        return $this->belongsTo(CasualPosition::class, 'casual_position_id');
    }

    public function casualShift(): BelongsTo
    {
        return $this->belongsTo(CasualShift::class, 'casual_shift_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
