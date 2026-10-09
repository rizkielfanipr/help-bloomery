<?php

use App\Actions\Rnd\InternalMemo\AddMenuToInternalMemoAction;
use App\Actions\Rnd\InternalMemo\CreateInternalMemoAction;
use App\Actions\Rnd\InternalMemo\CreateInternalMemoRevisionAction;
use App\Actions\Rnd\InternalMemo\DeleteInternalMemoAction;
use App\Actions\Rnd\InternalMemo\RemoveMenuFromInternalMemoAction;
use App\Actions\Rnd\InternalMemo\ResolveMemoBranchMappingsAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoBranchesAction;
use App\Actions\Rnd\InternalMemo\UpdateInternalMemoBrandAction;
use App\Jobs\SyncInternalMemoMenuCatalogJob;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoBranch;
use App\Models\RndInternalMemoMenuCatalog;
use App\Models\User;
use App\Services\Rnd\InternalMemo\InternalMemoBrandValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/**
 * docs/rnd-internal-memo-brand-prd.md §8, §13, §15, §16, §23.2–§23.6: Brand is metadata on the
 * Memo, every new write is BLSS, and nothing in the active flow writes legacy Branch rows.
 */
beforeEach(function () {
    Queue::fake();
    markInternalMemoCatalogSynced('BLS');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->brand = Brand::factory()->create(['name' => 'Bloomery Bakery']);
    $this->otherBrand = Brand::factory()->create(['name' => 'Arunika Coffee']);
    $this->operator = User::factory()->create(['is_active' => true]);
    $this->operator->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo', 'create rnd internal memo', 'update rnd internal memo', 'create rnd internal memo revision']);

    $this->createMemo = fn (array $overrides = [], ?User $actor = null): RndInternalMemo => app(CreateInternalMemoAction::class)->execute([
        'memo_number' => 'BR-'.fake()->unique()->numerify('####'), 'title' => 'Rilis', 'period_month' => '2026-09-01',
        'memo_date' => '2026-09-01', 'recipient' => '', 'sender' => '', 'subject' => 'Rilis', 'notes' => null,
        'brand_id' => $this->brand->id, ...$overrides,
    ], $actor ?? $this->operator);
});

describe('schema and model', function () {
    it('relates a Memo to its Brand and nulls brand_id on Master Brand delete while the snapshot stays', function () {
        $memo = RndInternalMemo::factory()->forBrand($this->brand)->create();

        expect($memo->brand->is($this->brand))->toBeTrue()->and($memo->brandLabel())->toBe('Bloomery Bakery');

        $this->brand->update(['name' => 'Rename']);
        expect($memo->fresh()->brandLabel())->toBe('Bloomery Bakery');

        $this->brand->delete();
        expect($memo->fresh()->brand_id)->toBeNull()
            ->and($memo->fresh()->brandLabel())->toBe('Bloomery Bakery');
    });

    it('enforces one active Memo per Brand, period, and revision at the database level', function () {
        RndInternalMemo::factory()->forBrand($this->brand)->create(['period_month' => '2026-09-01']);
        RndInternalMemo::factory()->forBrand($this->otherBrand)->create(['period_month' => '2026-09-01']);
        RndInternalMemo::factory()->forBrand($this->brand)->create(['period_month' => '2026-09-01', 'revision' => 2]);

        expect(fn () => RndInternalMemo::factory()->forBrand($this->brand)->create(['period_month' => '2026-09-01']))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('does not let a soft-deleted Memo block a new one, and legacy Memos without Brand never collide', function () {
        $old = RndInternalMemo::factory()->forBrand($this->brand)->create(['period_month' => '2026-09-01']);
        app(DeleteInternalMemoAction::class)->execute($old);

        RndInternalMemo::factory()->forBrand($this->brand)->create(['period_month' => '2026-09-01']);
        RndInternalMemo::factory()->withoutBrand()->count(2)->create(['period_month' => '2026-09-01']);

        expect(RndInternalMemo::query()->whereDate('period_month', '2026-09-01')->count())->toBe(3);
    });
});

describe('create, edit Brand, and revision', function () {
    it('creates with the Brand snapshot and Company Code BLSS, no Branch rows, and no sync', function () {
        $memo = ($this->createMemo)(['company_code' => 'BLO6', 'branch_ids' => [1, 2]]);

        expect($memo->only(['company_code', 'brand_id', 'brand_name_snapshot', 'revision']))
            ->toBe(['company_code' => 'BLSS', 'brand_id' => $this->brand->id, 'brand_name_snapshot' => 'Bloomery Bakery', 'revision' => 1])
            ->and(RndInternalMemoBranch::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('requires a valid Brand and turns a duplicate Brand period into a clear validation error', function () {
        expect(fn () => ($this->createMemo)(['brand_id' => null]))->toThrow(ValidationException::class, 'Pilih Brand Memo.')
            ->and(fn () => ($this->createMemo)(['brand_id' => 999999]))->toThrow(ValidationException::class, 'Brand yang dipilih tidak ditemukan.');

        ($this->createMemo)();
        expect(fn () => ($this->createMemo)())->toThrow(ValidationException::class, 'Memo Brand Bloomery Bakery untuk periode dan revisi ini sudah ada.');
    });

    it('maps a race on the unique index to the same validation error', function () {
        $validator = Mockery::mock(InternalMemoBrandValidator::class)->makePartial();
        $validator->shouldReceive('ensureUniquePeriod')->andReturnNull();
        $this->app->instance(InternalMemoBrandValidator::class, $validator);
        RndInternalMemo::factory()->forBrand($this->brand)->create(['period_month' => '2026-09-01']);

        expect(fn () => ($this->createMemo)())->toThrow(ValidationException::class, 'sudah ada');
    });

    it('refuses create and Brand edit without permission', function () {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('view rnd internal memo');
        $memo = ($this->createMemo)();

        expect(fn () => ($this->createMemo)([], $viewer))->toThrow(AuthorizationException::class)
            ->and(fn () => app(UpdateInternalMemoBrandAction::class)->execute($memo, $this->otherBrand->id, $viewer))->toThrow(AuthorizationException::class);
        expect($memo->fresh()->brand_id)->toBe($this->brand->id);
    });

    it('changes only brand_id and its snapshot when the Brand is edited, without any ESB request or sync', function () {
        Http::fake();
        $memo = ($this->createMemo)();
        $menu = $memo->menus()->create(['company_code' => 'BLSS', 'esb_menu_id' => 501, 'menu_name' => 'Croissant', 'esb_bom_id' => 42, 'release_date' => '2026-09-01', 'forecast_quantity' => 3, 'menu_snapshot' => []]);
        $before = DB::table('rnd_internal_memo_menus')->where('id', $menu->id)->first();

        app(UpdateInternalMemoBrandAction::class)->execute($memo, $this->otherBrand->id, $this->operator);

        expect($memo->fresh()->only(['brand_id', 'brand_name_snapshot', 'company_code', 'title']))
            ->toBe(['brand_id' => $this->otherBrand->id, 'brand_name_snapshot' => 'Arunika Coffee', 'company_code' => 'BLSS', 'title' => 'Rilis'])
            ->and(DB::table('rnd_internal_memo_menus')->where('id', $menu->id)->first())->toEqual($before);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    });

    it('copies the Brand into a revision, keeps BLSS for the revision and its Menus, and writes no Branch rows', function () {
        $memo = RndInternalMemo::factory()->forBrand($this->brand)->finalized()->create(['period_month' => '2026-09-01']);
        $memo->menus()->create(['company_code' => 'BLSS', 'esb_menu_id' => 501, 'menu_name' => 'Croissant', 'esb_bom_id' => 42, 'release_date' => '2026-09-01', 'forecast_quantity' => 3, 'menu_snapshot' => []]);

        $revision = app(CreateInternalMemoRevisionAction::class)->execute($memo, 'BR-REV-2', $this->operator);

        expect($revision->only(['brand_id', 'brand_name_snapshot', 'company_code', 'revision']))
            ->toBe(['brand_id' => $this->brand->id, 'brand_name_snapshot' => 'Bloomery Bakery', 'company_code' => 'BLSS', 'revision' => 2])
            ->and($revision->menus()->pluck('company_code')->all())->toBe(['BLSS'])
            ->and((float) $revision->menus()->sole()->forecast_quantity)->toBe(3.0)
            ->and($revision->branches()->count())->toBe(0);
    });

    it('asks a legacy Memo for a Brand before revising and refuses to normalize non-BLSS history', function () {
        $legacy = RndInternalMemo::factory()->withoutBrand()->finalized()->create();
        $nonBlss = RndInternalMemo::factory()->forBrand($this->brand)->finalized()->create(['company_code' => 'BLO6']);

        expect(fn () => app(CreateInternalMemoRevisionAction::class)->execute($legacy, 'REV-A', $this->operator))->toThrow(ValidationException::class, 'Tentukan Brand')
            ->and(fn () => app(CreateInternalMemoRevisionAction::class)->execute($nonBlss, 'REV-B', $this->operator))->toThrow(ValidationException::class, 'non-BLSS');
        expect($nonBlss->fresh()->company_code)->toBe('BLO6');
    });
});

describe('Add Menu from the BLSS catalog', function () {
    beforeEach(function () {
        config()->set('esb.core.base_url', 'https://esb.test/core');
        config()->set('esb.core.companies.BLSS', ['username' => 'blss-user', 'password' => 'blss-secret']);
        config()->set('esb.core.companies.BLO6', ['username' => 'blo6-user', 'password' => 'blo6-secret']);
        config()->set('esb.master_product.token', '');
        RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLSS', 'branch_code' => 'BLS', 'menu_id' => 501, 'menu_code' => 'MENU-501', 'menu_name' => 'Croissant', 'bom_id' => 42, 'flag_active' => true, 'raw_snapshot' => ['menuID' => 501]]);
        RndInternalMemoMenuCatalog::factory()->create(['company_code' => 'BLO6', 'branch_code' => 'BLE', 'menu_id' => 777, 'menu_name' => 'Menu BLO6', 'bom_id' => 77, 'flag_active' => true]);
    });

    it('needs no Branch, stores BLSS, writes no pivot, and resolves the BOM with BLSS credentials without the Brand', function () {
        Http::fake([
            'https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']]),
            'https://esb.test/core/product/bom/42' => Http::response(['status' => 'ok', 'result' => internalMemoBomDetailFixture('Menu', ['bomID' => 42])]),
        ]);
        $memo = RndInternalMemo::factory()->forBrand($this->brand)->create();

        $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, 501);

        expect($menu->only(['company_code', 'esb_menu_id', 'esb_bom_id', 'menu_name']))->toBe(['company_code' => 'BLSS', 'esb_menu_id' => 501, 'esb_bom_id' => 42, 'menu_name' => 'Croissant'])
            ->and($menu->sync_status->value)->toBe('synced')
            ->and(DB::table('rnd_internal_memo_menu_branches')->count())->toBe(0);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/auth/login') && ($request->data()['username'] ?? null) === 'blss-user');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->body().$request->url(), 'Bloomery Bakery') || ($request->data()['username'] ?? null) === 'blo6-user');
    });

    it('refuses Menus outside the active BLSS catalog and duplicates, keeping the Memo unchanged', function () {
        $memo = RndInternalMemo::factory()->forBrand($this->brand)->create();
        $memo->menus()->create(['company_code' => 'BLSS', 'esb_menu_id' => 501, 'menu_name' => 'Croissant', 'esb_bom_id' => 42, 'release_date' => now(), 'menu_snapshot' => []]);

        expect(fn () => app(AddMenuToInternalMemoAction::class)->execute($memo, 777))->toThrow(ValidationException::class, 'katalog ESB BLSS')
            ->and(fn () => app(AddMenuToInternalMemoAction::class)->execute($memo, 999))->toThrow(ValidationException::class)
            ->and(fn () => app(AddMenuToInternalMemoAction::class)->execute($memo, 501))->toThrow(ValidationException::class, 'sudah ada');
        expect($memo->menus()->count())->toBe(1);
    });

    it('keeps Menus and their data when the Brand changes and removes a Menu without Branch reconciliation', function () {
        Http::fake(['https://esb.test/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'token']])]);
        $memo = RndInternalMemo::factory()->forBrand($this->brand)->create();
        $menu = app(AddMenuToInternalMemoAction::class)->execute($memo, 501);

        app(UpdateInternalMemoBrandAction::class)->execute($memo, $this->otherBrand->id, $this->operator);
        expect($memo->menus()->pluck('id')->all())->toBe([$menu->id]);

        app(RemoveMenuFromInternalMemoAction::class)->execute($memo, $menu->id);
        expect($memo->menus()->count())->toBe(0);
    });
});

describe('legacy Brand backfill', function () {
    beforeEach(function () {
        // Distinct periods so two Memos resolved to the same Brand never collide by chance.
        $this->legacyPeriod = 0;
        $this->legacyMemo = function (array $branchBrandIds, string $company = 'BLSS'): RndInternalMemo {
            $memo = RndInternalMemo::factory()->withoutBrand()->create([
                'company_code' => $company,
                'period_month' => now()->startOfMonth()->addMonths(++$this->legacyPeriod)->toDateString(),
            ]);
            foreach ($branchBrandIds as $brandId) {
                RndInternalMemoBranch::factory()->create([
                    'rnd_internal_memo_id' => $memo->id,
                    'branch_id' => Branch::factory()->create(['brand_id' => $brandId])->id,
                ]);
            }

            return $memo;
        };
    });

    it('only reports in dry-run mode', function () {
        $memo = ($this->legacyMemo)([$this->brand->id]);

        $this->artisan('rnd:backfill-internal-memo-brands')->expectsOutputToContain('DRY-RUN')->assertSuccessful();

        expect($memo->fresh()->brand_id)->toBeNull();
    });

    it('resolves only deterministic Brands, never guesses, keeps existing Brands and non-BLSS history, and is idempotent', function () {
        $single = ($this->legacyMemo)([$this->brand->id]);
        $sameBrandTwice = ($this->legacyMemo)([$this->otherBrand->id, $this->otherBrand->id]);
        $noBrand = ($this->legacyMemo)([null]);
        $noBranch = ($this->legacyMemo)([]);
        $multiple = ($this->legacyMemo)([$this->brand->id, $this->otherBrand->id]);
        $nonBlss = ($this->legacyMemo)([$this->brand->id], 'BLO6');
        $existing = RndInternalMemo::factory()->forBrand($this->otherBrand)->create(['brand_name_snapshot' => null, 'period_month' => now()->startOfMonth()->subYears(2)->toDateString()]);
        RndInternalMemoBranch::factory()->create(['rnd_internal_memo_id' => $existing->id, 'branch_id' => Branch::factory()->create(['brand_id' => $this->brand->id])->id]);

        expect(Artisan::call('rnd:backfill-internal-memo-brands', ['--apply' => true, '--chunk' => 2]))->toBe(0);
        $output = Artisan::output();
        foreach (['scanned' => 7, 'resolved' => 2, 'already_resolved' => 1, 'unresolved_no_brand' => 2, 'unresolved_multiple_brands' => 1, 'skipped_non_blss' => 1, 'failed' => 0] as $key => $count) {
            expect($output)->toMatch('/\\|\\s+'.$key.'\\s+\\|\\s+'.$count.'\\s+\\|/');
        }
        expect($output)->toContain("#{$multiple->id} ");

        expect($single->fresh()->only(['brand_id', 'brand_name_snapshot']))->toBe(['brand_id' => $this->brand->id, 'brand_name_snapshot' => 'Bloomery Bakery'])
            ->and($sameBrandTwice->fresh()->brand_id)->toBe($this->otherBrand->id)
            ->and($noBrand->fresh()->brand_id)->toBeNull()
            ->and($noBranch->fresh()->brand_id)->toBeNull()
            ->and($multiple->fresh()->brand_id)->toBeNull()
            ->and($nonBlss->fresh()->only(['brand_id', 'company_code']))->toBe(['brand_id' => null, 'company_code' => 'BLO6'])
            ->and($existing->fresh()->only(['brand_id', 'brand_name_snapshot']))->toBe(['brand_id' => $this->otherBrand->id, 'brand_name_snapshot' => 'Arunika Coffee']);

        $snapshot = RndInternalMemo::withTrashed()->orderBy('id')->get(['id', 'brand_id', 'brand_name_snapshot', 'updated_at'])->toArray();
        $this->artisan('rnd:backfill-internal-memo-brands --apply')->assertSuccessful();
        expect(RndInternalMemo::withTrashed()->orderBy('id')->get(['id', 'brand_id', 'brand_name_snapshot', 'updated_at'])->toArray())->toBe($snapshot);
    });

    it('reports a per-Memo conflict as failed with a non-zero exit code instead of forcing it', function () {
        RndInternalMemo::factory()->forBrand($this->brand)->create(['period_month' => '2026-05-01']);
        $conflicting = ($this->legacyMemo)([$this->brand->id]);
        $conflicting->forceFill(['period_month' => '2026-05-01'])->save();

        $this->artisan('rnd:backfill-internal-memo-brands --apply')->assertFailed();

        expect($conflicting->fresh()->brand_id)->toBeNull();
    });
});

arch('active Memo Internal pages never touch the legacy multi-branch classes')
    ->expect('App\Filament\Helpdesk\Resources\RndInternalMemos')
    ->not->toUse([UpdateInternalMemoBranchesAction::class, ResolveMemoBranchMappingsAction::class, RndInternalMemoBranch::class, SyncInternalMemoMenuCatalogJob::class]);

arch('the Memo Internal domain Actions in the active flow never write legacy Branch rows')
    ->expect([CreateInternalMemoAction::class, UpdateInternalMemoBrandAction::class, AddMenuToInternalMemoAction::class, CreateInternalMemoRevisionAction::class])
    ->not->toUse([RndInternalMemoBranch::class, Branch::class, ResolveMemoBranchMappingsAction::class]);
