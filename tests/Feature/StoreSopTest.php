<?php

use App\Filament\Casual\Pages\StoreSopsPage;
use App\Filament\Helpdesk\Resources\StoreSops\Pages\ViewStoreSop;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\StoreSop;
use App\Models\StoreSopAssignment;
use App\Models\StoreSopCategory;
use App\Models\User;
use App\Services\StoreSopPublisher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('publishes by branch and allows every permitted branch user to receive the sop', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $targetBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $branchUser = User::factory()->create(['is_active' => true]);
    $branchUser->givePermissionTo('access employee app store sop');
    $branchUser->syncBranchAccess([$targetBranch->id], $targetBranch->id);
    $otherBranchUser = User::factory()->create(['is_active' => true]);
    $otherBranchUser->givePermissionTo('access employee app store sop');
    $otherBranchUser->syncBranchAccess([$otherBranch->id], $otherBranch->id);
    $publisher = User::factory()->create(['is_active' => true]);
    $publisher->assignRole('SUPERADMIN');
    $sop = StoreSop::factory()->create();
    $sop->branches()->attach($targetBranch);

    $count = app(StoreSopPublisher::class)->publish($sop, $publisher);
    app(StoreSopPublisher::class)->publish($sop->fresh(), $publisher);

    expect($count)->toBe(1)
        ->and($sop->fresh()->status)->toBe('published')
        ->and(StoreSopAssignment::count())->toBe(0)
        ->and($branchUser->notifications()->count())->toBeGreaterThan(0)
        ->and($otherBranchUser->notifications()->count())->toBe(0);

    $this->actingAs($branchUser);
    Livewire::test(StoreSopsPage::class)->assertSee($sop->title);

    expect(StoreSopAssignment::where('user_id', $branchUser->id)->count())->toBe(1)
        ->and(StoreSopAssignment::where('user_id', $otherBranchUser->id)->count())->toBe(0);
});

it('automatically exposes an existing branch sop to a newly permitted branch user', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $branch = Branch::factory()->create();
    $sop = StoreSop::factory()->create(['status' => 'published', 'published_at' => now()]);
    $sop->branches()->attach($branch);

    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('access employee app store sop');
    $user->syncBranchAccess([$branch->id], $branch->id);
    $this->actingAs($user);

    Livewire::test(StoreSopsPage::class)->assertSee($sop->title);

    expect(StoreSopAssignment::query()
        ->where('store_sop_id', $sop->id)
        ->where('branch_id', $branch->id)
        ->where('user_id', $user->id)
        ->exists())->toBeTrue();
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
        ->assertSee('Baru')
        ->call('acknowledge', $assignment->id)
        ->assertSee('Diterima')
        ->assertDontSee('Dipahami');

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
        ->assertSee('SOP Kategori')
        ->assertSee(route('filament.helpdesk.resources.store-sops.index'), false);

    $this->get(route('filament.helpdesk.resources.store-sops.create'))
        ->assertOk()
        ->assertSee('Nomor SOP')
        ->assertSee('Judul SOP')
        ->assertSee('Kategori SOP')
        ->assertSee('Brand')
        ->assertDontSee('Versi');

    $sop = StoreSop::factory()->create(['created_by' => $admin->id]);
    $this->get(route('filament.helpdesk.resources.store-sops.view', $sop))->assertOk();
    $this->get(route('filament.helpdesk.resources.store-sop-categories.index'))->assertOk();
    $this->get(route('filament.helpdesk.resources.store-sop-categories.create'))->assertOk();
});

it('stores sop category and brand as master data relationships', function () {
    $brand = Brand::query()->create(['name' => 'Bloomery']);
    $branch = Branch::factory()->create(['brand_id' => $brand->id]);
    $category = StoreSopCategory::factory()->create(['name' => 'Opening Store']);
    $sop = StoreSop::factory()->create([
        'brand_id' => $brand->id,
        'store_sop_category_id' => $category->id,
    ]);
    $sop->branches()->attach($branch);

    expect($sop->fresh()->brand->is($brand))->toBeTrue()
        ->and($sop->fresh()->category->is($category))->toBeTrue()
        ->and($sop->fresh()->branches)->toHaveCount(1)
        ->and($sop->fresh()->branches->first()->brand_id)->toBe($brand->id);
});

it('allows an authorized back office user to delete an sop and its receipts', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $branch = Branch::factory()->create();
    $sop = StoreSop::factory()->create(['status' => 'published', 'published_at' => now()]);
    $sop->branches()->attach($branch);
    StoreSopAssignment::create([
        'store_sop_id' => $sop->id,
        'branch_id' => $branch->id,
        'user_id' => $admin->id,
        'assigned_at' => now(),
    ]);
    $this->actingAs($admin);

    Livewire::test(ViewStoreSop::class, ['record' => $sop->getRouteKey()])
        ->callAction('delete')
        ->assertHasNoErrors();

    expect(StoreSop::whereKey($sop->id)->exists())->toBeFalse()
        ->and(StoreSopAssignment::where('store_sop_id', $sop->id)->exists())->toBeFalse();
});

it('supports filtering and searching on the casual store sops page', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $branch = Branch::factory()->create();
    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $supervisor->syncBranchAccess([$branch->id], $branch->id);

    $sopA = StoreSop::factory()->create(['title' => 'SOP Kebersihan Mesin', 'code' => 'SOP-001', 'status' => 'published', 'expires_at' => today()->addWeek(), 'published_at' => now()]);
    $sopB = StoreSop::factory()->create(['title' => 'SOP Penutupan Kasir', 'code' => 'SOP-002', 'status' => 'published', 'expires_at' => today()->subDay(), 'published_at' => now()]);

    $assignA = StoreSopAssignment::create(['store_sop_id' => $sopA->id, 'branch_id' => $branch->id, 'user_id' => $supervisor->id, 'assigned_at' => now()]);
    $assignB = StoreSopAssignment::create(['store_sop_id' => $sopB->id, 'branch_id' => $branch->id, 'user_id' => $supervisor->id, 'assigned_at' => now(), 'opened_at' => now(), 'acknowledged_at' => now()]);

    $this->actingAs($supervisor);

    Livewire::test(StoreSopsPage::class)
        ->assertSee('SOP Kebersihan Mesin')
        ->assertSee('SOP Penutupan Kasir')
        ->set('filter', 'ongoing')
        ->assertSee('SOP Kebersihan Mesin')
        ->assertDontSee('SOP Penutupan Kasir')
        ->set('filter', 'expired')
        ->assertDontSee('SOP Kebersihan Mesin')
        ->assertSee('SOP Penutupan Kasir')
        ->set('filter', 'all')
        ->set('search', 'Kebersihan')
        ->assertSee('SOP Kebersihan Mesin')
        ->assertDontSee('SOP Penutupan Kasir');
});

it('derives ongoing and expired statuses from the validity period', function () {
    $ongoingSop = StoreSop::factory()->create([
        'status' => 'published',
        'effective_date' => today()->subDay(),
        'expires_at' => today(),
        'published_at' => now(),
    ]);
    $expiredSop = StoreSop::factory()->create([
        'status' => 'published',
        'effective_date' => today()->subMonth(),
        'expires_at' => today()->subDay(),
        'published_at' => now(),
    ]);

    expect($ongoingSop->display_status)->toBe('ongoing')
        ->and($ongoingSop->display_status_label)->toBe('Ongoing')
        ->and($expiredSop->display_status)->toBe('expired')
        ->and($expiredSop->display_status_label)->toBe('Expired');
});

it('shows lifecycle status separately from the supervisor reading status', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $branch = Branch::factory()->create();
    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $supervisor->syncBranchAccess([$branch->id], $branch->id);

    foreach ([today()->addWeek(), today()->subDay()] as $expiresAt) {
        $sop = StoreSop::factory()->create([
            'status' => 'published',
            'effective_date' => today()->subMonth(),
            'expires_at' => $expiresAt,
            'published_at' => now(),
        ]);
        StoreSopAssignment::create([
            'store_sop_id' => $sop->id,
            'branch_id' => $branch->id,
            'user_id' => $supervisor->id,
            'assigned_at' => now(),
        ]);
    }

    $this->actingAs($supervisor);

    Livewire::test(StoreSopsPage::class)
        ->assertSee('Ongoing')
        ->assertSee('Expired')
        ->assertSee('Baru');
});
