<?php

use App\Enums\MarketingDesignBundleStatus;
use App\Filament\Helpdesk\Resources\Brands\Pages\ListBrands;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\MarketingDesignBundleResource;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\Pages\ListMarketingDesignBundles;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\Pages\ViewMarketingDesignBundle;
use App\Models\Brand;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignFile;
use App\Models\MarketingDesignItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    Storage::fake('b2');

    $this->designer = User::factory()->create(['is_active' => true]);
    $this->designer->assignRole('DESIGN_STAFF');
    $this->purchasing = User::factory()->create(['is_active' => true]);
    $this->purchasing->assignRole('PURCHASING_STAFF');
    $this->archiver = User::factory()->create(['is_active' => true]);
    $this->archiver->givePermissionTo('access backoffice', 'view design master bundles', 'archive design master bundles');
});

/** A bundle with one item and its design file. */
function filledDesignBundle(array $bundleAttributes = [], string $itemType = 'packaging_design'): MarketingDesignBundle
{
    $bundle = MarketingDesignBundle::factory()->create($bundleAttributes);
    $item = MarketingDesignItem::factory()->physical()->for($bundle, 'bundle')->create(['type' => $itemType, 'title' => 'Item '.$bundle->code]);
    MarketingDesignFile::factory()->image()->for($item, 'item')->create(['original_name' => 'desain-'.$bundle->code.'.jpg']);

    return $bundle;
}

function viewDesignPage(MarketingDesignBundle $bundle, User $user)
{
    test()->actingAs($user);

    return Livewire::test(ViewMarketingDesignBundle::class, ['record' => $bundle->getRouteKey()]);
}

function renderedButton(string $label): string
{
    return '/>\s*'.preg_quote($label, '/').'\s*</';
}

describe('navigation and access', function () {
    it('shows the menu only to users with the view permission', function () {
        $this->actingAs($this->purchasing)->get(MarketingDesignBundleResource::getUrl('index', panel: 'helpdesk'))
            ->assertOk()
            ->assertSee('Master Design');

        $stranger = User::factory()->create(['is_active' => true]);
        $stranger->givePermissionTo('access backoffice');

        $this->actingAs($stranger)->get(MarketingDesignBundleResource::getUrl('index', panel: 'helpdesk'))->assertForbidden();
    });

    it('has no create, edit, or delete page', function () {
        expect(array_keys(MarketingDesignBundleResource::getPages()))->toBe(['index', 'view'])
            ->and(MarketingDesignBundleResource::canDeleteAny())->toBeFalse();
    });

    it('returns 404 when purchasing opens an archived bundle by URL', function () {
        $archived = MarketingDesignBundle::factory()->archived()->create();

        $this->actingAs($this->purchasing)->get(MarketingDesignBundleResource::getUrl('view', ['record' => $archived], panel: 'helpdesk'))->assertNotFound();
        $this->actingAs($this->designer)->get(MarketingDesignBundleResource::getUrl('view', ['record' => $archived], panel: 'helpdesk'))->assertOk();
    });
});

/** IDs of the bundles on the current index page. */
function listedBundleIds($page): array
{
    return $page->instance()->bundles()->getCollection()->modelKeys();
}

describe('index', function () {
    it('renders the Project-style header, filter bar, and list', function () {
        $bundle = filledDesignBundle(['name' => 'Paket Lebaran']);

        $this->actingAs($this->designer);
        Livewire::test(ListMarketingDesignBundles::class)
            ->assertOk()
            ->assertSeeInOrder(['Brand Marketing', 'Master Design', 'Aktif', 'Diarsipkan', 'Semua Brand', 'Semua Kategori', 'Semua Tipe Item', 'Bundle', 'Brand', 'Kategori', 'Item', 'Diperbarui', 'Paket Lebaran'])
            ->assertSee($bundle->brand->name)
            ->assertSee('Halaman 1 / 1');
    });

    it('shows purchasing active bundles only, without the archived tab', function () {
        $active = MarketingDesignBundle::factory()->create();
        $archived = MarketingDesignBundle::factory()->archived()->create();

        $this->actingAs($this->purchasing);
        $page = Livewire::test(ListMarketingDesignBundles::class)
            ->assertActionHidden('createBundle')
            ->assertDontSee('Diarsipkan');
        expect(listedBundleIds($page))->toBe([$active->id]);

        $page->call('showArchived')->set('statusView', 'archived');
        expect(listedBundleIds($page))->toBe([$active->id]);
    });

    it('lets internal users switch to archived bundles', function () {
        $active = MarketingDesignBundle::factory()->create();
        $archived = MarketingDesignBundle::factory()->archived()->create();

        $this->actingAs($this->designer);
        $page = Livewire::test(ListMarketingDesignBundles::class);
        expect(listedBundleIds($page))->toBe([$active->id]);

        $page->call('showArchived');
        expect(listedBundleIds($page))->toBe([$archived->id]);

        $page->call('showActive');
        expect(listedBundleIds($page))->toBe([$active->id]);
    });

    it('defaults to all brands and filters by brand, category, type, and nature', function () {
        $brandA = Brand::factory()->create(['name' => 'Brand A']);
        $packaging = filledDesignBundle(['brand_id' => $brandA->id, 'category' => 'seasonal']);
        $social = filledDesignBundle(['category' => 'campaign'], 'social_media');

        $this->actingAs($this->designer);
        $ids = fn (array $filters) => listedBundleIds(tap(Livewire::test(ListMarketingDesignBundles::class), function ($page) use ($filters): void {
            foreach ($filters as $property => $value) {
                $page->set($property, $value);
            }
        }));

        expect($ids([]))->toEqualCanonicalizing([$packaging->id, $social->id])
            ->and($ids(['brandFilter' => (string) $brandA->id]))->toBe([$packaging->id])
            ->and($ids(['categoryFilter' => 'campaign']))->toBe([$social->id])
            ->and($ids(['itemTypeFilter' => 'social_media']))->toBe([$social->id])
            ->and($ids(['natureFilter' => 'physical']))->toBe([$packaging->id])
            ->and($ids(['natureFilter' => 'digital']))->toBe([$social->id]);
    });

    it('searches code, name, and description, and resets filters', function () {
        $target = MarketingDesignBundle::factory()->create(['code' => 'LEBARAN-27', 'normalized_code' => 'LEBARAN-27', 'name' => 'Paket Ramadan', 'description' => 'Hampers premium']);
        $other = MarketingDesignBundle::factory()->create(['code' => 'XMAS', 'normalized_code' => 'XMAS', 'name' => 'Natal']);

        $this->actingAs($this->designer);
        foreach (['lebaran', 'ramadan', 'premium'] as $term) {
            expect(listedBundleIds(Livewire::test(ListMarketingDesignBundles::class)->set('search', $term)))->toBe([$target->id]);
        }

        Livewire::test(ListMarketingDesignBundles::class)
            ->set('search', 'tidak-ada')
            ->assertSee('Bundle tidak ditemukan.')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSee('Natal');

        expect($other->exists)->toBeTrue();
    });

    it('counts items and paginates with numbered pages', function () {
        $bundle = MarketingDesignBundle::factory()->create(['updated_at' => now()->addDay()]);
        MarketingDesignItem::factory()->count(3)->for($bundle, 'bundle')->create();
        MarketingDesignBundle::factory()->count(21)->create();

        $this->actingAs($this->designer);
        $page = Livewire::test(ListMarketingDesignBundles::class);
        $bundles = $page->instance()->bundles();

        expect($bundles->total())->toBe(22)
            ->and($bundles->count())->toBe(20)
            ->and($bundles->getCollection()->firstWhere('id', $bundle->id)->items_count)->toBe(3);

        $page->assertSee('Halaman 1 / 2')->call('goToBundlesPage', 2)->assertSet('bundlesPage', 2)->assertSee('Halaman 2 / 2');
        expect($page->instance()->bundles()->count())->toBe(2);

        $page->call('nextBundlesPage')->assertSet('bundlesPage', 2)->set('search', 'x')->assertSet('bundlesPage', 1);
    });

    it('does not run more queries as bundles and items grow', function () {
        $countQueries = function (): int {
            $this->actingAs($this->designer);
            $page = Livewire::test(ListMarketingDesignBundles::class);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $page->instance()->bundles();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        filledDesignBundle();
        $few = $countQueries();

        foreach (range(1, 8) as $ignored) {
            filledDesignBundle();
        }

        expect($countQueries())->toBeLessThanOrEqual($few);
    });

    it('creates a bundle from the modal and opens its detail', function () {
        $brand = Brand::factory()->create();

        $this->actingAs($this->designer);
        Livewire::test(ListMarketingDesignBundles::class)
            ->callAction('createBundle', ['brand_id' => $brand->id, 'code' => 'promo a', 'name' => 'Promo A', 'category' => 'campaign'])
            ->assertHasNoFormErrors()
            ->assertRedirect(MarketingDesignBundleResource::getUrl('view', ['record' => MarketingDesignBundle::query()->sole()], panel: 'helpdesk'));

        expect(MarketingDesignBundle::query()->sole()->normalized_code)->toBe('PROMO-A');
    });

    it('asks only for brand, code, category, name, and description', function () {
        $this->actingAs($this->designer);

        Livewire::test(ListMarketingDesignBundles::class)
            ->mountAction('createBundle')
            ->assertFormFieldExists('code', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('valid_from', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('change_summary', 'mountedActionSchema0');
    });

    it('requires a brand in the modal', function () {
        $this->actingAs($this->designer);
        Livewire::test(ListMarketingDesignBundles::class)
            ->callAction('createBundle', ['code' => 'promo', 'name' => 'Promo', 'category' => 'campaign'])
            ->assertHasFormErrors(['brand_id' => 'required']);

        expect(MarketingDesignBundle::count())->toBe(0);
    });

    it('keeps the modal open and reports a duplicate code', function () {
        $brand = Brand::factory()->create();
        MarketingDesignBundle::factory()->for($brand)->create(['normalized_code' => 'PROMO']);

        $this->actingAs($this->designer);
        Livewire::test(ListMarketingDesignBundles::class)
            ->callAction('createBundle', ['brand_id' => $brand->id, 'code' => 'promo', 'name' => 'Promo', 'category' => 'campaign'])
            ->assertNotified('Bundle belum dapat dibuat');

        expect(MarketingDesignBundle::count())->toBe(1);
    });
});

describe('detail', function () {
    it('renders the section cards in the specified order without version, file, or audit cards', function () {
        $html = viewDesignPage(filledDesignBundle(), $this->designer)->assertOk()->html();

        $positions = array_map(fn (string $card): int|false => strpos($html, 'data-design-card="'.$card.'"'), ['summary', 'bundle', 'items', 'specs']);

        expect($positions)->not->toContain(false)
            ->and($positions)->toBe(collect($positions)->sort()->values()->all())
            ->and($html)->not->toContain('data-design-card="files"')
            ->and($html)->not->toContain('data-design-card="audit"')
            ->and($html)->not->toContain('Audit Activity')
            ->and($html)->not->toContain('data-design-card="version"')
            ->and($html)->not->toContain('data-design-card="history"');
    });

    it('gives purchasing a read-only view with the design file to download', function () {
        $bundle = filledDesignBundle();
        $page = viewDesignPage($bundle, $this->purchasing);

        $page->assertOk()->assertSee('desain-'.$bundle->code.'.jpg');

        foreach (['editBundle', 'archiveBundle', 'addItem'] as $action) {
            $page->assertActionHidden($action);
        }

        foreach (['Edit Bundle', 'Arsipkan', 'Pulihkan', 'Tambah Item', 'Ganti File', 'Unggah File', 'Isi Spesifikasi', 'Hapus'] as $label) {
            expect($page->html())->not->toMatch(renderedButton($label));
        }

        expect($page->html())->toContain('Unduh');
    });

    it('hides the download button and preview without the download permission', function () {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('access backoffice', 'view design master bundles');
        $bundle = filledDesignBundle();

        $html = viewDesignPage($bundle, $viewer)->assertSee('desain-'.$bundle->code.'.jpg')->html();

        expect($html)->not->toContain('marketing-design-files')->not->toMatch(renderedButton('Unduh'));
    });

    it('renders only the buttons each role may use', function () {
        $bundle = filledDesignBundle();

        $designer = viewDesignPage($bundle, $this->designer)->html();
        foreach (['Edit Bundle', 'Tambah Item', 'Ganti File', 'Isi Spesifikasi'] as $label) {
            expect($designer)->toMatch(renderedButton($label));
        }
        expect($designer)->not->toMatch(renderedButton('Arsipkan'));

        $archiver = viewDesignPage($bundle, $this->archiver)->html();
        expect($archiver)->toMatch(renderedButton('Arsipkan'))->not->toMatch(renderedButton('Tambah Item'));
    });

    it('shows empty states for a new bundle', function () {
        viewDesignPage(MarketingDesignBundle::factory()->create(), $this->designer)
            ->assertSee('Belum ada item desain pada bundle ini.')
            ->assertSee('Bundle ini tidak mempunyai item fisik.');
    });

    it('marks incomplete items for internal users only', function () {
        $item = MarketingDesignItem::factory()->create(['type' => 'sticker']);

        viewDesignPage($item->bundle, $this->designer)->assertSee('Belum Lengkap')->assertSee('Spesifikasi dimensi dan material belum diisi');
        viewDesignPage($item->bundle, $this->purchasing)->assertDontSee('Belum Lengkap');
    });

    it('lists each physical item with its identity and specification status', function () {
        $bundle = MarketingDesignBundle::factory()->create();
        $empty = MarketingDesignItem::factory()->for($bundle, 'bundle')->create(['type' => 'sticker', 'title' => 'Sticker Tutup']);
        MarketingDesignFile::factory()->for($empty, 'item')->create(['original_name' => 'tutup-final.pdf']);
        MarketingDesignItem::factory()->physical()->for($bundle, 'bundle')->create(['title' => 'Box Kue', 'finishing' => 'Doff']);
        MarketingDesignItem::factory()->for($bundle, 'bundle')->create(['type' => 'social_media', 'title' => 'Post Digital']);

        $html = viewDesignPage($bundle, $this->designer)
            ->assertSeeInOrder(['Spesifikasi Produksi', 'Sticker Tutup', 'tutup-final.pdf', 'Perlu Dilengkapi', 'Spesifikasi produksi belum diisi.', 'Box Kue', 'Spesifikasi Lengkap', 'Doff'])
            ->html();

        $specCard = substr($html, strpos($html, 'data-design-card="specs"'));
        expect($specCard)->not->toContain('Post Digital');
    });

    it('shows the item being specified inside the specification modal', function () {
        $item = MarketingDesignItem::factory()->create(['type' => 'sticker', 'title' => 'Sticker Tutup']);
        MarketingDesignFile::factory()->for($item, 'item')->create(['original_name' => 'tutup-final.pdf']);

        viewDesignPage($item->bundle, $this->designer)
            ->mountAction(TestAction::make('editSpecification')->arguments(['item' => $item->id]))
            ->assertSeeInOrder(['Spesifikasi Produksi', 'Sticker Tutup', 'Sticker', 'tutup-final.pdf'])
            ->assertFormFieldExists('dimensions', 'mountedActionSchema0');
    });

    it('never renders a signed URL or storage path', function () {
        $bundle = filledDesignBundle();
        $path = $bundle->items()->sole()->file->path;

        viewDesignPage($bundle, $this->purchasing)->assertDontSeeHtml($path)->assertDontSeeHtml('X-Amz-Signature');
    });

    it('wraps long names and offers a lazy preview', function () {
        $longName = str_repeat('NamaBundleSangatPanjang', 8);

        viewDesignPage(filledDesignBundle(['name' => $longName]), $this->purchasing)
            ->assertSee($longName)
            ->assertSeeHtml('break-words')
            ->assertSeeHtml('loading="lazy"');
    });

    it('does not run more queries as items and files grow', function () {
        $countQueries = function (MarketingDesignBundle $bundle): int {
            $this->actingAs($this->purchasing);
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(ViewMarketingDesignBundle::class, ['record' => $bundle->getRouteKey()])->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $small = filledDesignBundle();
        $large = filledDesignBundle();

        foreach (range(1, 6) as $ignored) {
            $item = MarketingDesignItem::factory()->physical()->for($large, 'bundle')->create();
            MarketingDesignFile::factory()->image()->for($item, 'item')->create(['uploaded_by' => $this->designer->id]);
        }

        $countQueries($small);

        expect($countQueries($large))->toBeLessThanOrEqual($countQueries($small) + 1);
    });
});

describe('editor', function () {
    it('adds an item with its file, edits it, fills the specification, replaces the file, and deletes it permanently', function () {
        $bundle = MarketingDesignBundle::factory()->create();
        $page = viewDesignPage($bundle, $this->designer);

        viewDesignPage($bundle, $this->designer)->mountAction('addItem')
            ->assertFormFieldExists('file', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('dimensions', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('role', 'mountedActionSchema0');

        $page->callAction('addItem', ['type' => 'packaging_design', 'title' => 'Box Kue', 'file' => UploadedFile::fake()->image('box.jpg')])
            ->assertHasNoFormErrors()
            ->assertNotified('Item ditambahkan')
            ->assertSee('Box Kue')
            ->assertSee('box.jpg');

        $item = $bundle->items()->sole();
        $file = $item->file;

        $page->callAction(TestAction::make('editItem')->arguments(['item' => $item->id]), ['type' => 'sticker', 'title' => 'Sticker Baru'])
            ->assertNotified('Item diperbarui');
        expect($item->fresh()->title)->toBe('Sticker Baru')->and($item->fresh()->isPhysical())->toBeTrue();

        $page->callAction(TestAction::make('editSpecification')->arguments(['item' => $item->id]), ['dimensions' => '5 x 5 cm', 'material_specification' => 'Vinyl'])
            ->assertNotified('Spesifikasi disimpan');
        expect($item->fresh()->dimensions)->toBe('5 x 5 cm');

        $page->callAction(TestAction::make('replaceFile')->arguments(['item' => $item->id]), ['file' => UploadedFile::fake()->create('baru.pdf', 10, 'application/pdf')])
            ->assertNotified('File desain disimpan');
        expect($file->fresh()->trashed())->toBeTrue()->and($item->fresh()->file->original_name)->toBe('baru.pdf');

        $page->callAction(TestAction::make('deleteItem')->arguments(['item' => $item->id]))->assertNotified('Item dihapus permanen');

        expect($item->fresh())->toBeNull();
    });

    it('labels the file action and download button by whether the item has a file', function () {
        $bundle = MarketingDesignBundle::factory()->create();
        $withFile = MarketingDesignItem::factory()->for($bundle, 'bundle')->create(['title' => 'Dengan File']);
        MarketingDesignFile::factory()->for($withFile, 'item')->create();
        MarketingDesignItem::factory()->for($bundle, 'bundle')->create(['title' => 'Tanpa File']);

        $html = viewDesignPage($bundle, $this->designer)->html();
        $withoutFileCard = substr($html, strpos($html, 'Tanpa File'));
        $withFileCard = substr($html, strpos($html, 'Dengan File'), strpos($html, 'Tanpa File') - strpos($html, 'Dengan File'));

        expect($withFileCard)->toMatch(renderedButton('Ganti File'))->toContain('marketing-design-files')
            ->and($withoutFileCard)->toMatch(renderedButton('Unggah File'))->toContain('Belum ada file desain')->toMatch('/<button[^>]*disabled[^>]*title="Belum ada file desain"|title="Belum ada file desain"[^>]*disabled/');
    });

    it('uploads the first file for an item that has none', function () {
        $item = MarketingDesignItem::factory()->create();

        viewDesignPage($item->bundle, $this->designer)
            ->callAction(TestAction::make('replaceFile')->arguments(['item' => $item->id, 'hasFile' => false]), ['file' => UploadedFile::fake()->image('pertama.jpg')])
            ->assertNotified('File desain disimpan');

        expect($item->fresh()->file->original_name)->toBe('pertama.jpg');
    });

    it('requires the design file when adding an item', function () {
        $bundle = MarketingDesignBundle::factory()->create();

        viewDesignPage($bundle, $this->designer)
            ->callAction('addItem', ['type' => 'other', 'title' => 'Tanpa File'])
            ->assertHasFormErrors(['file' => 'required']);

        expect(MarketingDesignItem::count())->toBe(0);
    });

    it('edits the bundle with the brand locked', function () {
        $bundle = MarketingDesignBundle::factory()->create();

        viewDesignPage($bundle, $this->designer)
            ->callAction('editBundle', ['code' => 'BARU', 'name' => 'Nama Baru', 'category' => 'event', 'brand_id' => Brand::factory()->create()->id])
            ->assertNotified('Bundle diperbarui');

        expect($bundle->fresh()->name)->toBe('Nama Baru')->and($bundle->fresh()->brand_id)->toBe($bundle->brand_id);
    });

    it('reports a rejected upload and stores nothing', function () {
        $bundle = MarketingDesignBundle::factory()->create();

        viewDesignPage($bundle, $this->designer)
            ->callAction('addItem', ['type' => 'other', 'title' => 'SVG', 'file' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')]);

        expect(MarketingDesignItem::count())->toBe(0)
            ->and(MarketingDesignFile::count())->toBe(0)
            ->and(Storage::disk('b2')->allFiles())->toBeEmpty();
    });

    it('cannot reach an item of another bundle through tampered arguments', function () {
        $bundle = MarketingDesignBundle::factory()->create();
        $foreign = MarketingDesignItem::factory()->create(['title' => 'Asing']);

        expect(fn () => viewDesignPage($bundle, $this->designer)->callAction(TestAction::make('deleteItem')->arguments(['item' => $foreign->id])))
            ->toThrow(ModelNotFoundException::class);

        expect($foreign->fresh())->not->toBeNull();
    });

    it('archives with a required reason and restores, hiding the bundle from purchasing meanwhile', function () {
        $bundle = filledDesignBundle();

        viewDesignPage($bundle, $this->archiver)
            ->callAction('archiveBundle', [])
            ->assertHasFormErrors(['reason' => 'required']);

        viewDesignPage($bundle, $this->archiver)
            ->callAction('archiveBundle', ['reason' => 'Selesai'])
            ->assertNotified('Bundle diarsipkan')
            ->assertSee('Diarsipkan: Selesai');

        $this->actingAs($this->purchasing)->get(MarketingDesignBundleResource::getUrl('view', ['record' => $bundle], panel: 'helpdesk'))->assertNotFound();
        viewDesignPage($bundle->fresh(), $this->designer)->assertActionHidden('addItem')->assertActionHidden('editBundle');

        viewDesignPage($bundle->fresh(), $this->archiver)->callAction('restoreBundle')->assertNotified('Bundle dipulihkan');

        expect($bundle->fresh()->status)->toBe(MarketingDesignBundleStatus::Active);
    });

    it('hides SUPERADMIN buttons the bundle status does not allow', function () {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('SUPERADMIN');

        $active = viewDesignPage(MarketingDesignBundle::factory()->create(), $admin)->assertActionVisible('addItem')->assertActionHidden('restoreBundle');
        expect($active->html())->not->toMatch(renderedButton('Pulihkan'));

        viewDesignPage(MarketingDesignBundle::factory()->archived()->create(), $admin)->assertActionHidden('addItem')->assertActionVisible('restoreBundle');
    });
});

it('refuses to delete a brand that owns design bundles', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $brand = Brand::factory()->create();
    MarketingDesignBundle::factory()->for($brand)->create();
    $free = Brand::factory()->create();

    $this->actingAs($admin);
    Livewire::test(ListBrands::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($brand))
        ->assertNotified('Brand tidak dapat dihapus')
        ->callAction(TestAction::make(DeleteAction::class)->table($free));

    expect(Brand::query()->whereKey($brand->id)->exists())->toBeTrue()
        ->and(Brand::query()->whereKey($free->id)->exists())->toBeFalse();
});
