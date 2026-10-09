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
        // A standard-column checkbox may toggle several permissions at once (value="12,13").
        if (preg_match('/type="checkbox"\s+value="(?:\d+,)*'.$ids[$name].'(?:,\d+)*"/', $html) !== 1) {
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

it('ticks list and detail access together in the View column and puts update permissions under Edit', function () {
    $ids = Permission::pluck('id', 'name');
    $html = Livewire::test(CreateRole::class)->html();

    expect($html)->toContain('value="'.$ids['view any rnd internal memo'].','.$ids['view rnd internal memo'].'"')
        ->and($html)->toContain('title="Update rnd internal memo"')
        ->and($html)->toContain('toggle('.$ids['update rnd internal memo'].')')
        ->and($html)->not->toContain('<span>View rnd internal memo</span>')
        ->and($html)->not->toContain('<span>Update rnd internal memo</span>')
        ->and($html)->toContain('<span>View all branch rnd project tasks</span>');
});
