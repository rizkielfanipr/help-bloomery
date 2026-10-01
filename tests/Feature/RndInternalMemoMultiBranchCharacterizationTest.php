<?php

use App\Actions\Rnd\InternalMemo\AddMenuToInternalMemoAction;
use App\Enums\RndInternalMemoStatus;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Models\User;
use App\Services\Rnd\InternalMemo\InternalMemoBomResolver;
use App\Services\Rnd\InternalMemo\InternalMemoMenuCatalogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * docs/rnd-internal-memo-multi-branch-prd.md Phase 1 ("Safety net") — these lock the EXACT
 * current (single-Company-Code BLSS) contracts Phase 2-6 are about to change, so any accidental
 * drift during the refactor fails loudly here instead of silently. Each test's docblock states
 * which Phase is expected to deliberately break it, and what the new behavior should be —
 * when that happens, update the assertion here as part of that phase's own commit, not as an
 * unrelated fix.
 */
beforeEach(function () {
    config()->set('esb.base_url', 'https://esb.test');
    config()->set('esb.tokens.BLSS', 'static-blss-token');
    config()->set('esb.core.base_url', 'https://esb.test/core');
    config()->set('esb.core.companies.BLSS', ['username' => 'memo-user', 'password' => 'memo-secret']);
    // Blanked deliberately: InternalMemoProductEnricher's Product-detail lookup (a separate ESB
    // Master Product integration, not ESB Core) must fail immediately on its own missing-token
    // guard rather than attempting any HTTP call at all, so the ESB-request-count characterization
    // test below counts only BOM resolution, not product enrichment's own credential fallback.
    config()->set('esb.master_product.token', '');
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('characterizes InternalMemoBomResolver::resolve() as taking only a Menu, with no Company Code parameter', function () {
    // Phase 6 ("BOM dan Product multi-company") is expected to change this: the resolver must
    // start using the Company Code the Menu actually came from, not a hardcoded constant. This
    // test exists so that change is a deliberate, visible diff to this file, not a silent one.
    $method = new ReflectionMethod(InternalMemoBomResolver::class, 'resolve');
    $parameters = $method->getParameters();

    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getType()?->getName())->toBe(RndInternalMemoMenu::class);
});

it('characterizes catalog synchronization as requiring Company Code and Branch Code context', function () {
    $method = new ReflectionMethod(InternalMemoMenuCatalogService::class, 'allForContext');
    $parameterNames = array_map(fn (ReflectionParameter $p): string => $p->getName(), $method->getParameters());

    expect($parameterNames)->toBe(['companyCode', 'branchCode']);
});

it('characterizes the exact ESB request count for adding a Menu with a resolvable BOM: login + one BOM detail fetch', function () {
    // Baseline for Phase 6: if multi-company BOM resolution adds a per-Menu credential lookup or
    // an extra round-trip, this count will move and must be updated deliberately.
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);
    Http::fake([
        'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
        'https://esb.test/core/product/bom/42' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', ['bomID' => 42])]),
    ]);

    app(AddMenuToInternalMemoAction::class)->execute($memo, [
        'menuID' => 501, 'menuCode' => 'MENU-501', 'menuName' => 'Croissant Butter',
        'categoryDetail' => 'Pastry', 'bomID' => 42, 'bomName' => 'BOM-42',
        'flagActive' => true, 'hasBom' => true, 'raw' => ['menuID' => 501, 'menuName' => 'Croissant Butter'],
    ]);

    Http::assertSentCount(2);
});

it('characterizes RndInternalMemoPolicy::view() as permission-only today, with no branch concept at all', function () {
    // Phase 3 ("Form create/edit dan Policy") is expected to add branch-scoped authorization —
    // today, any two memos (regardless of any hypothetical difference between them) are equally
    // visible to any user holding the view permission, because no branch field exists on the
    // model yet. This is the "before" baseline that Phase 3's new restriction must visibly change.
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo('view rnd internal memo');
    $memoA = RndInternalMemo::factory()->create();
    $memoB = RndInternalMemo::factory()->create(['period_month' => now()->addMonths(2)->startOfMonth()]);

    expect($viewer->can('view', $memoA))->toBeTrue()
        ->and($viewer->can('view', $memoB))->toBeTrue();
});

it('characterizes opening the Memo detail page with one Menu as making no ESB calls when nothing is refreshed', function () {
    // Baseline for Phase 4/7: opening an existing Memo's detail page today reads only local
    // snapshot data (menu_snapshot/bom_snapshot columns) and performs zero ESB HTTP calls. The
    // new Menu-picker catalog sync must not regress this by eagerly calling ESB on every open.
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $operator = User::factory()->create(['is_active' => true]);
    $operator->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo']);
    $this->actingAs($operator);

    $memo = RndInternalMemo::factory()->create();
    $memo->menus()->create([
        'esb_menu_id' => 501, 'menu_code' => 'MENU-501', 'menu_name' => 'Croissant Butter',
        'esb_bom_id' => 42, 'release_date' => now(), 'sync_status' => 'synced', 'synced_at' => now(),
        'menu_snapshot' => ['menuID' => 501, 'menuName' => 'Croissant Butter'],
        'bom_snapshot' => ['bomID' => 42],
    ]);

    Http::fake(); // any real call would be a stray-request failure, proving zero calls happen
    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->assertOk();

    Http::assertNothingSent();
});
