<?php

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Models\User;
use App\Services\PermissionSynchronizer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
});

it('gives every configured permission a checkbox on the role form', function () {
    $html = Livewire::test(CreateRole::class)->html();
    $ids = Permission::pluck('id', 'name');
    $missing = [];

    foreach (app(PermissionSynchronizer::class)->configuredPermissions() as $name) {
        if (preg_match('/type="checkbox"\s+value="'.$ids[$name].'"/', $html) !== 1) {
            $missing[] = $name;
        }
    }

    expect($missing)->toBe([]);
});

it('shows permissions without a standard action column under Izin Khusus', function () {
    Livewire::test(CreateRole::class)
        ->assertSee('Izin Khusus')
        ->assertSee('Recalculate basket sizes')
        ->assertSee('Export basket sizes')
        ->assertSee('Review sales reports as supervisor')
        ->assertSee('Review sales reports as finance')
        ->assertSee('View store sop reports');
});
