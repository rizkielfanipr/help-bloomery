<?php

use App\Filament\Casual\Pages\ItemJournalPage;
use App\Filament\Casual\Pages\LauncherPage;
use App\Models\Branch;
use App\Models\BriefingBranchSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
});

it('redirects guests to login', function () {
    get(route('filament.casual.home'))
        ->assertRedirect();
});

it('serves the launcher at the casual domain root and redirects the legacy URL', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

    expect(LauncherPage::getUrl(panel: 'casual'))->toBe(route('filament.casual.home'));

    get(route('filament.casual.home'))
        ->assertOk()
        ->assertSee('Absensi');

    get(route('filament.casual.pages.launcher-page'))
        ->assertRedirect(route('filament.casual.home'));
});

it('shows Absensi tile for CASUAL_STAFF', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Absensi');
});

it('shows Driver tile for DRIVER', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('DRIVER');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Driver');
});

it('shows Teknisi tile for TECHNICIAN', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('TECHNICIAN');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Teknisi');
});

it('shows Quality Control tile for QUALITY_CONTROL', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('QUALITY_CONTROL');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Quality Control');
});

it('shows the standalone Item Journal tile to STORE_STAFF without Quality Control access', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('STORE_STAFF');

    actingAs($user);

    $tiles = collect(Livewire::test(LauncherPage::class)->instance()->tiles());
    expect($tiles->pluck('label'))->toContain('Item Journal')
        ->not->toContain('Quality Control')
        ->and($tiles->firstWhere('label', 'Item Journal')['href'])->toBe(ItemJournalPage::getUrl(panel: 'casual'));
});

it('only shows the Item Journal tile with the tile permission and journal view access', function (array $permissions, bool $visible) {
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo($permissions);

    actingAs($user);

    expect(collect(Livewire::test(LauncherPage::class)->instance()->tiles())->pluck('label')->contains('Item Journal'))->toBe($visible);
})->with([
    'tile and view' => [['access employee app item journal', 'view quality control item journals'], true],
    'tile only' => [['access employee app item journal'], false],
    'journal actions without tile' => [['access employee app quality control', 'view quality control item journals', 'create quality control item journals', 'submit quality control item journals'], false],
]);

it('hides role-specific tiles from users without that role', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertDontSee('Driver')
        ->assertDontSee('Teknisi');
});

it('only shows feature tiles granted by permissions', function () {
    $user = User::factory()->create(['is_active' => true]);
    $role = Role::findOrCreate('PURCHASING_STAFF', 'web');
    $role->givePermissionTo('access employee app purchasing');
    $user->assignRole($role);

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Purchasing')
        ->assertDontSee('Daily Briefing')
        ->assertDontSee('Request Desain')
        ->assertDontSee('Request ERP');
});

it('only shows the Briefing tile to an eligible participant store staff', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $user->assignRole('STORE_STAFF');
    actingAs($user);

    $component = Livewire::test(LauncherPage::class);
    expect(collect($component->instance()->tiles())->pluck('label'))->not->toContain('Briefing');

    BriefingBranchSetting::factory()->create(['branch_id' => $branch->id]);

    $component = Livewire::test(LauncherPage::class);
    expect(collect($component->instance()->tiles())->pluck('label'))->toContain('Briefing');
});

it('blocks direct Employee App URLs for menus that are not granted', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASUAL_STAFF');

    actingAs($user)
        ->get(route('filament.casual.pages.purchase-request-page'))
        ->assertForbidden();
});

it('shows all available tiles for SUPERADMIN', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SUPERADMIN');

    actingAs($user);

    Livewire::test(LauncherPage::class)
        ->assertSee('Absensi')
        ->assertSee('Driver')
        ->assertSee('Teknisi')
        ->assertSee('Receiving')
        ->assertSee('Item Journal')
        ->assertDontSee('Penerimaan Barang');
});
