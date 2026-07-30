<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create([
        'is_active' => true,
        'access_all_branches' => true,
    ]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
});

it('keeps one primary branch and multiple accessible branches in sync', function () {
    $atelier = Branch::factory()->create(['name' => 'Bloomery Atelier']);
    $utama = Branch::factory()->create(['name' => 'Toko Utama']);
    $user = User::factory()->create([
        'username' => 'BLOBRANCHTEST',
        'branch_id' => $utama->id,
        'is_active' => true,
    ]);

    $user->syncBranchAccess([$atelier->id, $utama->id], $atelier->id);

    expect($user->fresh()->branch_id)->toBe($atelier->id)
        ->and($user->primaryBranchId())->toBe($atelier->id)
        ->and($user->accessibleBranchIds()->sort()->values()->all())
        ->toBe(collect([$atelier->id, $utama->id])->sort()->values()->all())
        ->and($user->canAccessBranch($utama->id))->toBeTrue();

    $this->assertDatabaseHas('user_branches', [
        'user_id' => $user->id,
        'branch_id' => $atelier->id,
        'is_primary' => true,
    ]);
});

it('uses one unified branch input and persists its primary branch', function () {
    $atelier = Branch::factory()->create(['name' => 'Bloomery Atelier']);
    $utama = Branch::factory()->create(['name' => 'Toko Utama']);
    $user = User::factory()->create([
        'username' => 'BLOBRANCHFORM',
        'branch_id' => $utama->id,
        'is_active' => true,
    ]);
    $user->syncBranchAccess([$utama->id], $utama->id);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->assertSee('Akses Cabang')
        ->assertDontSee('Cabang yang Diawasi')
        ->fillForm([
            'branch_access_ids' => [$atelier->id, $utama->id],
            'primary_branch_id' => $atelier->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->branch_id)->toBe($atelier->id)
        ->and($user->accessibleBranchIds()->sort()->values()->all())
        ->toBe(collect([$atelier->id, $utama->id])->sort()->values()->all());
});
