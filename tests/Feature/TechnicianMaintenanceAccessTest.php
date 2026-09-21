<?php

use App\Filament\Casual\Pages\TechnicianMaintenancePage;
use App\Models\User;
use App\Services\PermissionSynchronizer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

const MAINTENANCE_PERMISSIONS = [
    'view technician monthly maintenance',
    'create technician monthly maintenance',
    'edit technician monthly maintenance',
    'delete technician monthly maintenance',
];

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->technician = User::factory()->create(['is_active' => true]);
    $this->technician->assignRole('TECHNICIAN');
    Filament::setCurrentPanel(Filament::getPanel('casual'));
});

it('registers the monthly maintenance permissions so sync creates them and roles can grant them', function () {
    expect(app(PermissionSynchronizer::class)->configuredPermissions()->all())->toContain(...MAINTENANCE_PERMISSIONS);
});

it('grants technicians the maintenance permissions on environments that never had them', function () {
    Permission::whereIn('name', MAINTENANCE_PERMISSIONS)->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->technician->fresh());
    Livewire::test(TechnicianMaintenancePage::class)->assertForbidden();

    (require database_path('migrations/2026_09_21_082905_grant_technician_monthly_maintenance_permissions.php'))->up();

    $this->actingAs($this->technician->fresh());
    Livewire::test(TechnicianMaintenancePage::class)->assertSuccessful();
    expect($this->technician->fresh()->can('create technician monthly maintenance'))->toBeTrue();
});

it('keeps the grant idempotent when technicians already have the permissions', function () {
    $migration = require database_path('migrations/2026_09_21_082905_grant_technician_monthly_maintenance_permissions.php');

    $migration->up();
    $migration->up();

    expect(Permission::whereIn('name', MAINTENANCE_PERMISSIONS)->count())->toBe(4);
});
