<?php

use App\Enums\MarketingDesignBundleStatus;
use App\Enums\MarketingDesignFileRole;
use App\Enums\MarketingDesignVersionStatus;
use App\Filament\Helpdesk\Resources\Brands\Pages\ListBrands;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\MarketingDesignBundleResource;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\Pages\ListMarketingDesignBundles;
use App\Filament\Helpdesk\Resources\MarketingDesignBundles\Pages\ViewMarketingDesignBundle;
use App\Models\Brand;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignBundleVersion;
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
    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->givePermissionTo(
        'access backoffice',
        'view design master bundles', 'create design master bundles', 'edit design master bundles', 'upload design master files',
        'review design master bundles', 'publish design master bundles', 'archive design master bundles',
    );
});

function publishedDesignBundle(array $bundleAttributes = [], string $itemType = 'packaging_design'): MarketingDesignBundle
{
    $bundle = MarketingDesignBundle::factory()->create($bundleAttributes);
    $version = MarketingDesignBundleVersion::factory()->published()->for($bundle, 'bundle')->create();
    $item = MarketingDesignItem::factory()->physical()->for($version, 'version')->create(['type' => $itemType, 'title' => 'Item '.$bundle->code]);

    foreach (MarketingDesignFileRole::cases() as $role) {
        MarketingDesignFile::factory()->role($role)->for($item, 'item')->create(['original_name' => $role->value.'-file.'.($role === MarketingDesignFileRole::Preview ? 'jpg' : 'zip')]);
    }

    return $bundle;
}

function viewDesignPage(MarketingDesignBundle $bundle, User $user)
{
    test()->actingAs($user);

    return Livewire::test(ViewMarketingDesignBundle::class, ['record' => $bundle->getRouteKey()]);
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

    it('returns 404 when purchasing opens a draft or archived bundle by URL', function () {
        $draft = MarketingDesignBundleVersion::factory()->create()->bundle;
        $archived = publishedDesignBundle();
        $archived->update(['status' => MarketingDesignBundleStatus::Archived]);

        $this->actingAs($this->purchasing)->get(MarketingDesignBundleResource::getUrl('view', ['record' => $draft], panel: 'helpdesk'))->assertNotFound();
        $this->actingAs($this->purchasing)->get(MarketingDesignBundleResource::getUrl('view', ['record' => $archived], panel: 'helpdesk'))->assertNotFound();
    });
});

describe('index', function () {
    it('shows purchasing active published bundles only, and designers their drafts', function () {
        $published = publishedDesignBundle();
        $draft = MarketingDesignBundleVersion::factory()->create()->bundle;
        $archived = publishedDesignBundle();
        $archived->update(['status' => MarketingDesignBundleStatus::Archived]);

        $this->actingAs($this->purchasing);
        Livewire::test(ListMarketingDesignBundles::class)
            ->assertCanSeeTableRecords([$published])
            ->assertCanNotSeeTableRecords([$draft, $archived])
            ->assertActionHidden('createBundle')
            ->filterTable('status', MarketingDesignBundleStatus::Archived->value)
            ->assertCanNotSeeTableRecords([$archived]);

        $this->actingAs($this->designer);
        Livewire::test(ListMarketingDesignBundles::class)
            ->assertCanSeeTableRecords([$published, $draft])
            ->assertCanNotSeeTableRecords([$archived])
            ->filterTable('status', MarketingDesignBundleStatus::Archived->value)
            ->assertCanSeeTableRecords([$archived]);
    });

    it('defaults to all brands and filters by brand, category, type, nature, version status, and period', function () {
        $brandA = Brand::factory()->create(['name' => 'Brand A']);
        $packaging = publishedDesignBundle(['brand_id' => $brandA->id, 'category' => 'seasonal', 'valid_from' => '2027-01-01', 'valid_until' => '2027-02-01']);
        $social = publishedDesignBundle(['category' => 'campaign', 'valid_from' => '2027-06-01', 'valid_until' => '2027-07-01'], 'social_media');
        $draft = MarketingDesignBundleVersion::factory()->create()->bundle;

        $this->actingAs($this->designer);
        $table = fn () => Livewire::test(ListMarketingDesignBundles::class);

        $table()->assertTableFilterExists('brand_id')->assertCanSeeTableRecords([$packaging, $social, $draft]);
        $table()->filterTable('brand_id', $brandA->id)->assertCanSeeTableRecords([$packaging])->assertCanNotSeeTableRecords([$social]);
        $table()->filterTable('category', 'campaign')->assertCanSeeTableRecords([$social])->assertCanNotSeeTableRecords([$packaging]);
        $table()->filterTable('item_type', 'social_media')->assertCanSeeTableRecords([$social])->assertCanNotSeeTableRecords([$packaging]);
        $table()->filterTable('nature', 'physical')->assertCanSeeTableRecords([$packaging])->assertCanNotSeeTableRecords([$social]);
        $table()->filterTable('nature', 'digital')->assertCanSeeTableRecords([$social])->assertCanNotSeeTableRecords([$packaging]);
        $table()->filterTable('version_status', MarketingDesignVersionStatus::Draft->value)->assertCanSeeTableRecords([$draft])->assertCanNotSeeTableRecords([$packaging]);
        $table()->filterTable('period', ['from' => '2027-06-15', 'until' => '2027-06-20'])->assertCanSeeTableRecords([$social])->assertCanNotSeeTableRecords([$packaging]);
    });

    it('searches code and name', function () {
        $target = publishedDesignBundle(['code' => 'LEBARAN-27', 'normalized_code' => 'LEBARAN-27', 'name' => 'Paket Ramadan']);
        $other = publishedDesignBundle(['code' => 'XMAS', 'normalized_code' => 'XMAS', 'name' => 'Natal']);

        $this->actingAs($this->designer);
        Livewire::test(ListMarketingDesignBundles::class)->filterTable('search', ['value' => 'lebaran'])->assertCanSeeTableRecords([$target])->assertCanNotSeeTableRecords([$other]);
        Livewire::test(ListMarketingDesignBundles::class)->filterTable('search', ['value' => 'ramadan'])->assertCanSeeTableRecords([$target])->assertCanNotSeeTableRecords([$other]);
    });

    it('paginates on the server', function () {
        MarketingDesignBundle::factory()->count(12)->create();

        $this->actingAs($this->designer);
        Livewire::test(ListMarketingDesignBundles::class)->assertCountTableRecords(12)->assertSee('12');
        expect(Livewire::test(ListMarketingDesignBundles::class)->instance()->getTableRecords()->count())->toBe(10);
    });

    it('does not run more queries as bundles and items grow', function () {
        $countQueries = function (): int {
            $this->actingAs($this->designer);
            Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(ListMarketingDesignBundles::class)->instance()->getTableRecords();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        publishedDesignBundle();
        $few = $countQueries();

        foreach (range(1, 8) as $ignored) {
            publishedDesignBundle();
        }
        $many = $countQueries();

        expect($many)->toBeLessThanOrEqual($few);
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

        $small = publishedDesignBundle();
        $large = publishedDesignBundle();
        $version = $large->publishedVersion;

        foreach (range(1, 6) as $ignored) {
            $item = MarketingDesignItem::factory()->physical()->for($version, 'version')->create();
            MarketingDesignFile::factory()->count(3)->for($item, 'item')->create(['uploaded_by' => $this->designer->id]);
        }

        $countQueries($small);

        expect($countQueries($large))->toBeLessThanOrEqual($countQueries($small) + 1);
    });

    it('renders the section cards in the specified order', function () {
        $html = viewDesignPage(publishedDesignBundle(), $this->designer)->assertOk()->html();

        $positions = array_map(fn (string $card): int|false => strpos($html, 'data-design-card="'.$card.'"'), ['summary', 'bundle', 'version', 'items', 'specs', 'files', 'history', 'audit']);

        expect($positions)->not->toContain(false)
            ->and($positions)->toBe(collect($positions)->sort()->values()->all());
    });

    it('gives purchasing a read-only published view without source files', function () {
        $bundle = publishedDesignBundle();
        $draft = MarketingDesignBundleVersion::factory()->for($bundle, 'bundle')->create(['version_number' => 2, 'change_summary' => 'RAHASIA-DRAFT']);
        MarketingDesignItem::factory()->for($draft, 'version')->create(['title' => 'ITEM-DRAFT']);

        $page = viewDesignPage($bundle, $this->purchasing);

        $page->assertOk()
            ->assertSee('production-file.zip')
            ->assertSee('preview-file.jpg')
            ->assertDontSee('source-file.zip')
            ->assertDontSee('supporting-file.zip')
            ->assertDontSee('RAHASIA-DRAFT')
            ->assertDontSee('ITEM-DRAFT')
            ->assertDontSeeHtml('id="design-version-select"');

        foreach (['editBundle', 'createRevision', 'archiveBundle', 'editVersionSummary', 'submitVersion', 'approveVersion', 'requestRevision', 'publishVersion', 'addItem'] as $action) {
            $page->assertActionHidden($action);
        }

        foreach (['Edit Bundle', 'Buat Revisi', 'Arsipkan', 'Pulihkan', 'Edit Ringkasan', 'Kirim untuk Review', 'Setujui', 'Kembalikan untuk Revisi', 'Tambah Item', 'Unggah File', 'Ganti', 'Lepas'] as $label) {
            $page->assertDontSeeHtml('>'.$label);
            expect($page->html())->not->toMatch('/>\s*'.preg_quote($label, '/').'\s*</');
        }

        expect($page->html())->toContain('Unduh');
    });

    it('ignores a tampered version selection from purchasing', function () {
        $bundle = publishedDesignBundle();
        $draft = MarketingDesignBundleVersion::factory()->for($bundle, 'bundle')->create(['version_number' => 2]);
        MarketingDesignItem::factory()->for($draft, 'version')->create(['title' => 'ITEM-DRAFT']);

        viewDesignPage($bundle, $this->purchasing)
            ->set('selectedVersionId', $draft->id)
            ->assertDontSee('ITEM-DRAFT');
    });

    it('ignores a version selection from another bundle', function () {
        $bundle = publishedDesignBundle();
        $foreign = MarketingDesignBundleVersion::factory()->create();
        MarketingDesignItem::factory()->for($foreign, 'version')->create(['title' => 'ITEM-ASING']);

        viewDesignPage($bundle, $this->designer)
            ->set('selectedVersionId', $foreign->id)
            ->assertDontSee('ITEM-ASING');
    });

    it('lets a designer switch revisions and compare metadata', function () {
        $bundle = publishedDesignBundle();
        $published = $bundle->publishedVersion;
        $base = $published->items()->sole();
        $revision = MarketingDesignBundleVersion::factory()->for($bundle, 'bundle')->create(['version_number' => 2, 'cloned_from_version_id' => $published->id]);
        MarketingDesignItem::factory()->physical()->for($revision, 'version')->create(['title' => 'Judul Berubah', 'type' => $base->type, 'cloned_from_item_id' => $base->id]);
        MarketingDesignItem::factory()->for($revision, 'version')->create(['title' => 'Item Tambahan']);

        viewDesignPage($bundle, $this->designer)
            ->assertSee('Judul Berubah')
            ->assertSee('Perbandingan metadata dengan V1')
            ->assertSee('Item baru: Item Tambahan')
            ->assertSee('Judul Berubah berubah pada: Judul')
            ->call('selectVersion', $published->id)
            ->assertSee($base->title)
            ->assertDontSee('Item Tambahan');
    });

    it('shows empty states for a new draft', function () {
        $bundle = MarketingDesignBundleVersion::factory()->create()->bundle;

        viewDesignPage($bundle, $this->designer)
            ->assertSee('Belum ada item desain pada version ini.')
            ->assertSee('Version ini tidak mempunyai item fisik.')
            ->assertSee('Belum ada file production.')
            ->assertSee('Version belum mempunyai item.');
    });

    it('never renders a signed URL or storage path', function () {
        $bundle = publishedDesignBundle();
        $path = $bundle->publishedVersion->items()->sole()->files()->first()->path;

        viewDesignPage($bundle, $this->purchasing)->assertDontSeeHtml($path)->assertDontSeeHtml('X-Amz-Signature');
    });

    it('wraps long names without a fixed width and offers a lazy preview', function () {
        $longName = str_repeat('NamaBundleSangatPanjang', 8);
        $bundle = publishedDesignBundle(['name' => $longName]);

        viewDesignPage($bundle, $this->purchasing)
            ->assertSee($longName)
            ->assertSeeHtml('break-words')
            ->assertSeeHtml('loading="lazy"');
    });
});

describe('editor workflow', function () {
    it('runs add item, upload, submit, review, publish, and revision through the page', function () {
        $bundle = MarketingDesignBundleVersion::factory()->create()->bundle;
        $version = $bundle->versions()->sole();

        $designerPage = viewDesignPage($bundle, $this->designer);
        $designerPage->callAction('addItem', ['type' => 'social_media', 'title' => 'Post IG'])
            ->assertHasNoFormErrors()
            ->assertNotified('Item ditambahkan')
            ->assertSee('Post IG');

        $item = $version->items()->sole();
        $designerPage->callAction(TestAction::make('uploadFile')->arguments(['item' => $item->id]), [
            'role' => MarketingDesignFileRole::Preview->value,
            'file' => UploadedFile::fake()->image('post.jpg'),
        ])->assertHasNoFormErrors()->assertNotified('File diunggah')->assertSee('post.jpg');

        expect($item->files()->sole()->role)->toBe(MarketingDesignFileRole::Preview);

        $designerPage->callAction('submitVersion')->assertNotified('Version dikirim untuk review');
        expect($version->fresh()->status)->toBe(MarketingDesignVersionStatus::InReview);
        $designerPage->assertActionHidden('addItem')->assertActionHidden('submitVersion');

        viewDesignPage($bundle->fresh(), $this->manager)
            ->callAction('approveVersion', ['note' => 'Bagus'])
            ->assertNotified('Version disetujui')
            ->callAction('publishVersion', ['note' => 'Rilis'])
            ->assertNotified('Version dipublikasikan');

        expect($version->fresh()->status)->toBe(MarketingDesignVersionStatus::Published);

        viewDesignPage($bundle->fresh(), $this->designer)
            ->assertActionHidden('addItem')
            ->callAction('createRevision', ['change_summary' => 'Warna baru'])
            ->assertNotified('Draft revisi dibuat');

        expect($bundle->versions()->count())->toBe(2)
            ->and($bundle->versions()->where('version_number', 2)->sole()->items()->count())->toBe(1);
    });

    it('returns a version with a required reason and reopens it', function () {
        $version = MarketingDesignBundleVersion::factory()->status(MarketingDesignVersionStatus::InReview)->create();

        viewDesignPage($version->bundle, $this->manager)
            ->callAction('requestRevision', [])
            ->assertHasFormErrors(['reason' => 'required']);

        viewDesignPage($version->bundle, $this->manager)
            ->callAction('requestRevision', ['reason' => 'Warna salah'])
            ->assertNotified('Version dikembalikan untuk revisi')
            ->callAction('reopenVersion')
            ->assertNotified('Version kembali menjadi draft');

        expect($version->fresh()->status)->toBe(MarketingDesignVersionStatus::Draft);
    });

    it('reports readiness failures on submit and keeps the draft', function () {
        $version = MarketingDesignBundleVersion::factory()->create();

        viewDesignPage($version->bundle, $this->designer)
            ->callAction('submitVersion')
            ->assertNotified('Belum dapat diproses');

        expect($version->fresh()->status)->toBe(MarketingDesignVersionStatus::Draft);
    });

    it('reports a rejected upload and stores nothing', function () {
        $version = MarketingDesignBundleVersion::factory()->create();
        $item = MarketingDesignItem::factory()->for($version, 'version')->create();

        viewDesignPage($version->bundle, $this->designer)
            ->callAction(TestAction::make('uploadFile')->arguments(['item' => $item->id]), [
                'role' => MarketingDesignFileRole::Source->value,
                'file' => UploadedFile::fake()->create('desain.pdf', 10, 'application/pdf'),
            ]);

        expect(MarketingDesignFile::count())->toBe(0)
            ->and(Storage::disk('b2')->allFiles())->toBeEmpty();
    });

    it('edits, replaces, and removes on a draft', function () {
        $version = MarketingDesignBundleVersion::factory()->create();
        $item = MarketingDesignItem::factory()->for($version, 'version')->create();
        $file = MarketingDesignFile::factory()->for($item, 'item')->create();

        $page = viewDesignPage($version->bundle, $this->designer);
        $page->callAction(TestAction::make('editItem')->arguments(['item' => $item->id]), ['type' => 'sticker', 'title' => 'Sticker Baru', 'dimensions' => '5 x 5 cm'])
            ->assertNotified('Item diperbarui');
        expect($item->fresh()->isPhysical())->toBeTrue()->and($item->fresh()->dimensions)->toBe('5 x 5 cm');

        $page->callAction(TestAction::make('replaceFile')->arguments(['file' => $file->id]), ['file' => UploadedFile::fake()->image('baru.jpg')])
            ->assertNotified('File diganti');
        expect($file->fresh()->trashed())->toBeTrue();

        $replacement = $item->files()->sole();
        $page->callAction(TestAction::make('removeFile')->arguments(['file' => $replacement->id]))->assertNotified('File dilepas');
        $page->callAction(TestAction::make('removeItem')->arguments(['item' => $item->id]))->assertNotified('Item dilepas');

        expect($item->fresh()->trashed())->toBeTrue();
    });

    it('cannot reach an item of another version through tampered arguments', function () {
        $version = MarketingDesignBundleVersion::factory()->create();
        $foreign = MarketingDesignItem::factory()->for(MarketingDesignBundleVersion::factory(), 'version')->create(['title' => 'Asing']);

        expect(fn () => viewDesignPage($version->bundle, $this->designer)->callAction(TestAction::make('removeItem')->arguments(['item' => $foreign->id])))
            ->toThrow(ModelNotFoundException::class);

        expect($foreign->fresh()->trashed())->toBeFalse();
    });

    it('archives and restores from the page, hiding it from purchasing', function () {
        $bundle = publishedDesignBundle();

        viewDesignPage($bundle, $this->manager)
            ->callAction('archiveBundle', ['reason' => 'Selesai'])
            ->assertNotified('Bundle diarsipkan')
            ->assertSee('Diarsipkan: Selesai')
            ->assertActionHidden('createRevision')
            ->callAction('restoreBundle')
            ->assertNotified('Bundle dipulihkan');

        expect($bundle->fresh()->status)->toBe(MarketingDesignBundleStatus::Active);
    });

    it('renders only the buttons each role may use on a draft', function () {
        $version = MarketingDesignBundleVersion::factory()->create();
        MarketingDesignFile::factory()->for(MarketingDesignItem::factory()->for($version, 'version'), 'item')->create();

        $designer = viewDesignPage($version->bundle, $this->designer)->html();
        foreach (['Kirim untuk Review', 'Tambah Item', 'Unggah File', 'Ganti'] as $label) {
            expect($designer)->toMatch('/>\s*'.$label.'\s*</');
        }
        foreach (['Setujui', 'Publish', 'Arsipkan'] as $label) {
            expect($designer)->not->toMatch('/>\s*'.$label.'\s*</');
        }

        $reviewer = User::factory()->create(['is_active' => true]);
        $reviewer->givePermissionTo('access backoffice', 'view design master bundles', 'review design master bundles');
        $version->update(['status' => MarketingDesignVersionStatus::InReview]);

        $reviewerHtml = viewDesignPage($version->bundle->fresh(), $reviewer)->html();
        expect($reviewerHtml)->toMatch('/>\s*Setujui\s*</')->not->toMatch('/>\s*Tambah Item\s*</')->not->toMatch('/>\s*Publish\s*</');
    });

    it('hides lifecycle buttons from SUPERADMIN when the status does not allow them', function () {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('SUPERADMIN');
        $version = MarketingDesignBundleVersion::factory()->create();

        $page = viewDesignPage($version->bundle, $admin)
            ->assertActionVisible('submitVersion')
            ->assertActionHidden('publishVersion')
            ->assertActionHidden('approveVersion')
            ->assertActionHidden('restoreBundle');

        expect($page->html())->not->toMatch('/>\s*Publish\s*</')->not->toMatch('/>\s*Pulihkan\s*</');
    });

    it('offers a revision only when no other version is open', function () {
        $bundle = publishedDesignBundle();
        viewDesignPage($bundle, $this->designer)->assertActionVisible('createRevision');

        MarketingDesignBundleVersion::factory()->for($bundle, 'bundle')->create(['version_number' => 2]);
        viewDesignPage($bundle->fresh(), $this->designer)->assertActionHidden('createRevision');
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
