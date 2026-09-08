<?php

use App\Filament\Casual\Pages\StoreSopsPage;
use App\Models\Branch;
use App\Models\StoreSop;
use App\Models\StoreSopAssignment;
use App\Models\User;
use App\Services\StoreSopPublisher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('assigns a published sop only to supervisors handling its target branches', function () {
    $targetBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $supervisor->syncBranchAccess([$targetBranch->id], $targetBranch->id);
    $otherSupervisor = User::factory()->create(['is_active' => true]);
    $otherSupervisor->assignRole('SUPERVISOR_STORE');
    $otherSupervisor->syncBranchAccess([$otherBranch->id], $otherBranch->id);
    $publisher = User::factory()->create(['is_active' => true]);
    $publisher->assignRole('SUPERADMIN');
    $sop = StoreSop::factory()->create();
    $sop->branches()->attach($targetBranch);

    $count = app(StoreSopPublisher::class)->publish($sop, $publisher);
    app(StoreSopPublisher::class)->publish($sop->fresh(), $publisher);

    expect($count)->toBe(1)
        ->and($sop->fresh()->status)->toBe('published')
        ->and(StoreSopAssignment::where('user_id', $supervisor->id)->count())->toBe(1)
        ->and(StoreSopAssignment::where('user_id', $otherSupervisor->id)->count())->toBe(0)
        ->and($supervisor->notifications()->count())->toBeGreaterThan(0);
});

it('lets the assigned supervisor acknowledge a store sop', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $branch = Branch::factory()->create();
    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $supervisor->syncBranchAccess([$branch->id], $branch->id);
    $sop = StoreSop::factory()->create(['status' => 'published', 'published_at' => now()]);
    $assignment = StoreSopAssignment::create(['store_sop_id' => $sop->id, 'branch_id' => $branch->id, 'user_id' => $supervisor->id, 'assigned_at' => now()]);
    $this->actingAs($supervisor);

    Livewire::test(StoreSopsPage::class)
        ->assertSee($sop->title)
        ->call('acknowledge', $assignment->id);

    expect($assignment->fresh()->opened_at)->not->toBeNull()
        ->and($assignment->fresh()->acknowledged_at)->not->toBeNull();
});

it('shows the sop store menu under operational in the custom back office sidebar', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    $this->get(route('filament.helpdesk.resources.store-sops.index'))
        ->assertOk()
        ->assertSee('Operational')
        ->assertSee('SOP Store')
        ->assertSee(route('filament.helpdesk.resources.store-sops.index'), false);

    $this->get(route('filament.helpdesk.resources.store-sops.create'))->assertOk();

    $sop = StoreSop::factory()->create(['created_by' => $admin->id]);
    $this->get(route('filament.helpdesk.resources.store-sops.view', $sop))->assertOk();
});

it('supports filtering and searching on the casual store sops page', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $branch = Branch::factory()->create();
    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $supervisor->syncBranchAccess([$branch->id], $branch->id);

    $sopA = StoreSop::factory()->create(['title' => 'SOP Kebersihan Mesin', 'code' => 'SOP-001', 'status' => 'published', 'published_at' => now()]);
    $sopB = StoreSop::factory()->create(['title' => 'SOP Penutupan Kasir', 'code' => 'SOP-002', 'status' => 'published', 'published_at' => now()]);

    $assignA = StoreSopAssignment::create(['store_sop_id' => $sopA->id, 'branch_id' => $branch->id, 'user_id' => $supervisor->id, 'assigned_at' => now()]);
    $assignB = StoreSopAssignment::create(['store_sop_id' => $sopB->id, 'branch_id' => $branch->id, 'user_id' => $supervisor->id, 'assigned_at' => now(), 'opened_at' => now(), 'acknowledged_at' => now()]);

    $this->actingAs($supervisor);

    Livewire::test(StoreSopsPage::class)
        ->assertSee('SOP Kebersihan Mesin')
        ->assertSee('SOP Penutupan Kasir')
        ->set('filter', 'unread')
        ->assertSee('SOP Kebersihan Mesin')
        ->assertDontSee('SOP Penutupan Kasir')
        ->set('filter', 'acknowledged')
        ->assertDontSee('SOP Kebersihan Mesin')
        ->assertSee('SOP Penutupan Kasir')
        ->set('filter', 'all')
        ->set('search', 'Kebersihan')
        ->assertSee('SOP Kebersihan Mesin')
        ->assertDontSee('SOP Penutupan Kasir');
});

it('allows archiving a published sop', function () {
    $sop = StoreSop::factory()->create(['status' => 'published', 'published_at' => now()]);
    $sop->update(['status' => 'archived']);

    expect($sop->fresh()->status)->toBe('archived');
});
