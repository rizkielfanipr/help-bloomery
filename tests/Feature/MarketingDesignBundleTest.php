<?php

use App\Actions\BrandMarketing\DesignMaster\ArchiveDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\CreateDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\DeleteDesignItemAction;
use App\Actions\BrandMarketing\DesignMaster\RestoreDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\SaveDesignItemAction;
use App\Actions\BrandMarketing\DesignMaster\SaveDesignItemSpecificationAction;
use App\Actions\BrandMarketing\DesignMaster\UpdateDesignBundleAction;
use App\Enums\MarketingDesignAuditEvent;
use App\Enums\MarketingDesignBundleStatus;
use App\Models\Brand;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignFile;
use App\Models\MarketingDesignItem;
use App\Models\MarketingMaterialFulfillment;
use App\Models\RndProject;
use App\Models\RndProjectMarketingMaterial;
use App\Models\RndProjectProduct;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignCompleteness;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actor = User::factory()->create(['is_active' => true]);
    $this->actor->givePermissionTo(
        'view design master bundles', 'create design master bundles', 'edit design master bundles',
        'upload design master files', 'archive design master bundles',
    );
    $this->brand = Brand::factory()->create();
});

function designBundleData(array $overrides = []): array
{
    return $overrides + [
        'brand_id' => test()->brand->id,
        'code' => 'lebaran 2027',
        'name' => 'Lebaran 2027',
        'category' => 'seasonal',
    ];
}

it('creates a bundle under one brand with audit', function () {
    $bundle = app(CreateDesignBundleAction::class)->execute($this->actor, designBundleData(['description' => 'Paket hampers']));

    expect($bundle->code)->toBe('lebaran 2027')
        ->and($bundle->normalized_code)->toBe('LEBARAN-2027')
        ->and($bundle->status)->toBe(MarketingDesignBundleStatus::Active)
        ->and($bundle->description)->toBe('Paket hampers')
        ->and($bundle->created_by)->toBe($this->actor->id)
        ->and($bundle->auditLogs()->sole()->event)->toBe(MarketingDesignAuditEvent::BundleCreated);
});

it('requires an existing brand', function (mixed $brandId) {
    expect(fn () => app(CreateDesignBundleAction::class)->execute($this->actor, designBundleData(['brand_id' => $brandId])))
        ->toThrow(ValidationException::class);

    expect(MarketingDesignBundle::count())->toBe(0);
})->with([null, 999999]);

it('keeps the code unique per brand, case-insensitively, and allows it on another brand', function () {
    $create = app(CreateDesignBundleAction::class);
    $create->execute($this->actor, designBundleData(['code' => 'Promo A']));

    expect(fn () => $create->execute($this->actor, designBundleData(['code' => '  promo   a '])))
        ->toThrow(ValidationException::class, 'Kode bundle sudah dipakai');

    $other = $create->execute($this->actor, designBundleData(['code' => 'PROMO-A', 'brand_id' => Brand::factory()->create()->id]));

    expect($other->normalized_code)->toBe('PROMO-A');
});

it('enforces code uniqueness in the database', function () {
    MarketingDesignBundle::factory()->for($this->brand)->create(['normalized_code' => 'SAME']);

    expect(fn () => MarketingDesignBundle::factory()->for($this->brand)->create(['normalized_code' => 'SAME']))
        ->toThrow(QueryException::class);
});

it('updates the bundle but keeps the brand fixed', function () {
    $bundle = app(CreateDesignBundleAction::class)->execute($this->actor, designBundleData());

    $updated = app(UpdateDesignBundleAction::class)->execute($this->actor, $bundle, designBundleData(['brand_id' => Brand::factory()->create()->id, 'name' => 'Nama Baru']));

    expect($updated->brand_id)->toBe($this->brand->id)
        ->and($updated->name)->toBe('Nama Baru')
        ->and($bundle->auditLogs()->where('event', MarketingDesignAuditEvent::BundleUpdated)->sole()->metadata['fields'])->toContain('name');
});

describe('items', function () {
    it('adds, updates, specifies, and deletes items with audit', function () {
        Storage::fake('b2');
        $bundle = MarketingDesignBundle::factory()->create();
        $save = app(SaveDesignItemAction::class);

        $item = $save->create($this->actor, $bundle, ['type' => 'sticker', 'title' => 'Sticker Tutup'], UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));
        $second = $save->create($this->actor, $bundle, ['type' => 'social_media', 'title' => 'Post IG'], UploadedFile::fake()->image('b.jpg'));
        $save->update($this->actor, $bundle, $item, ['type' => 'sticker', 'title' => 'Sticker Baru']);
        app(SaveDesignItemSpecificationAction::class)->execute($this->actor, $bundle, $item, ['dimensions' => ' 5 x 5 cm ', 'material_specification' => 'Vinyl']);
        app(DeleteDesignItemAction::class)->execute($this->actor, $second);

        expect($item->fresh()->title)->toBe('Sticker Baru')
            ->and($item->fresh()->dimensions)->toBe('5 x 5 cm')
            ->and($item->fresh()->material_specification)->toBe('Vinyl')
            ->and($second->sort_order)->toBeGreaterThan($item->sort_order)
            ->and($second->fresh())->toBeNull()
            ->and($bundle->items()->pluck('id')->all())->toBe([$item->id])
            ->and($bundle->auditLogs()->pluck('event')->all())->toContain(
                MarketingDesignAuditEvent::ItemAdded,
                MarketingDesignAuditEvent::ItemUpdated,
                MarketingDesignAuditEvent::ItemSpecificationUpdated,
                MarketingDesignAuditEvent::ItemDeleted,
            );
    });

    it('validates the item type and name before storing anything', function () {
        Storage::fake('b2');
        $bundle = MarketingDesignBundle::factory()->create();
        $file = UploadedFile::fake()->create('a.pdf', 10, 'application/pdf');

        expect(fn () => app(SaveDesignItemAction::class)->create($this->actor, $bundle, ['type' => 'billboard', 'title' => 'X'], $file))->toThrow(ValidationException::class)
            ->and(fn () => app(SaveDesignItemAction::class)->create($this->actor, $bundle, ['type' => 'other', 'title' => ''], $file))->toThrow(ValidationException::class);

        expect(Storage::disk('b2')->allFiles())->toBeEmpty();
    });

    it('cannot update an item of another bundle', function () {
        $bundle = MarketingDesignBundle::factory()->create();
        $foreign = MarketingDesignItem::factory()->create(['title' => 'Asing']);

        expect(fn () => app(SaveDesignItemAction::class)->update($this->actor, $bundle, $foreign, ['type' => 'other', 'title' => 'Diubah']))
            ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

        expect($foreign->fresh()->title)->toBe('Asing');
    });

    it('accepts a specification only for physical items', function () {
        $digital = MarketingDesignItem::factory()->create(['type' => 'social_media']);

        expect(fn () => app(SaveDesignItemSpecificationAction::class)->execute($this->actor, $digital->bundle, $digital, ['dimensions' => '1080 px']))
            ->toThrow(ValidationException::class, 'hanya untuk item fisik');
    });

    it('derives physical from the type, ignoring browser input', function () {
        $item = MarketingDesignItem::factory()->create();
        $updated = app(SaveDesignItemAction::class)->update($this->actor, $item->bundle, $item, ['type' => 'packaging_design', 'title' => 'Box', 'is_physical' => false]);

        expect($updated->isPhysical())->toBeTrue();
    });
});

describe('completeness', function () {
    it('flags a missing file and specification without blocking', function () {
        $sticker = MarketingDesignItem::factory()->create(['type' => 'sticker']);
        $digital = MarketingDesignItem::factory()->create(['type' => 'social_media']);
        MarketingDesignFile::factory()->for($digital, 'item')->create();

        expect(app(MarketingDesignCompleteness::class)->itemIssues($sticker->load('file')))
            ->toBe(['File desain belum ada', 'Spesifikasi dimensi dan material belum diisi'])
            ->and(app(MarketingDesignCompleteness::class)->itemIssues($digital->load('file')))->toBe([]);
    });

    it('passes a complete physical item', function () {
        $item = MarketingDesignItem::factory()->physical()->create();
        MarketingDesignFile::factory()->for($item, 'item')->create();

        expect(app(MarketingDesignCompleteness::class)->itemIssues($item->load('file')))->toBe([]);
    });
});

describe('archive', function () {
    it('keeps items, files, and audit, and blocks changes until restored', function () {
        $item = MarketingDesignItem::factory()->create();
        MarketingDesignFile::factory()->for($item, 'item')->create();
        $bundle = $item->bundle;

        app(ArchiveDesignBundleAction::class)->execute($this->actor, $bundle, 'Campaign selesai');
        $archived = $bundle->fresh();

        expect($archived->status)->toBe(MarketingDesignBundleStatus::Archived)
            ->and($archived->archive_reason)->toBe('Campaign selesai')
            ->and($archived->archived_by)->toBe($this->actor->id)
            ->and(MarketingDesignItem::count())->toBe(1)
            ->and(MarketingDesignFile::count())->toBe(1);

        expect(fn () => app(SaveDesignItemAction::class)->update($this->actor, $archived, $item, ['type' => 'other', 'title' => 'X']))->toThrow(AuthorizationException::class)
            ->and(fn () => app(UpdateDesignBundleAction::class)->execute($this->actor, $archived, designBundleData()))->toThrow(AuthorizationException::class)
            ->and(fn () => app(ArchiveDesignBundleAction::class)->execute($this->actor, $archived, 'lagi'))->toThrow(AuthorizationException::class);

        app(RestoreDesignBundleAction::class)->execute($this->actor, $archived);

        expect($bundle->fresh()->status)->toBe(MarketingDesignBundleStatus::Active)
            ->and($bundle->fresh()->archived_at)->toBeNull()
            ->and(app(SaveDesignItemAction::class)->update($this->actor, $bundle->fresh(), $item, ['type' => 'other', 'title' => 'Baru'])->title)->toBe('Baru')
            ->and($bundle->auditLogs()->pluck('event')->all())->toContain(MarketingDesignAuditEvent::BundleArchived, MarketingDesignAuditEvent::BundleRestored);
    });

    it('requires a reason', function () {
        expect(fn () => app(ArchiveDesignBundleAction::class)->execute($this->actor, MarketingDesignBundle::factory()->create(), '  '))
            ->toThrow(ValidationException::class);
    });
});

it('binds SUPERADMIN to the bundle status, because Gate::before must not skip it', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole(Role::findOrCreate('SUPERADMIN', 'web'));
    $archived = MarketingDesignBundle::factory()->archived()->create();
    $active = MarketingDesignBundle::factory()->create();

    $archivedItem = MarketingDesignItem::factory()->for($archived, 'bundle')->create();
    $activeItem = MarketingDesignItem::factory()->for($active, 'bundle')->create();

    expect(fn () => app(SaveDesignItemAction::class)->update($admin, $archived, $archivedItem, ['type' => 'other', 'title' => 'X']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ArchiveDesignBundleAction::class)->execute($admin, $archived, 'lagi'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RestoreDesignBundleAction::class)->execute($admin, $active))->toThrow(AuthorizationException::class);

    expect(app(SaveDesignItemAction::class)->update($admin, $active, $activeItem, ['type' => 'other', 'title' => 'Boleh'])->title)->toBe('Boleh');
});

it('never deletes bundles or audit', function () {
    $bundle = app(CreateDesignBundleAction::class)->execute($this->actor, designBundleData());
    $log = $bundle->auditLogs()->first();

    expect(fn () => $bundle->delete())->toThrow(LogicException::class)
        ->and(fn () => $log->update(['reason' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('leaves Project R&D marketing materials and fulfillment untouched and reuses their vocabulary', function () {
    $project = RndProject::create(['name' => 'Project A', 'start_date' => now(), 'end_date' => now()->addMonth()]);
    $product = RndProjectProduct::create(['rnd_project_id' => $project->id, 'name' => 'Produk A']);
    $material = RndProjectMarketingMaterial::create(['rnd_project_product_id' => $product->id, 'type' => 'packaging_design', 'title' => 'Kemasan', 'file_path' => 'rnd/marketing-materials/1/1/a.pdf', 'original_name' => 'a.pdf']);
    $before = $material->fresh()->getAttributes();

    Storage::fake('b2');
    $bundle = app(CreateDesignBundleAction::class)->execute($this->actor, designBundleData());
    app(SaveDesignItemAction::class)->create($this->actor, $bundle, ['type' => 'packaging_design', 'title' => 'Box'], UploadedFile::fake()->create('box.pdf', 10, 'application/pdf'));
    app(ArchiveDesignBundleAction::class)->execute($this->actor, $bundle, 'selesai');

    expect($material->fresh()->getAttributes())->toBe($before)
        ->and(RndProjectMarketingMaterial::count())->toBe(1)
        ->and(MarketingMaterialFulfillment::count())->toBe(0)
        ->and(RndProjectMarketingMaterial::TYPES)->toBe([
            'packaging_design' => 'Design Packaging',
            'sticker' => 'Sticker',
            'product_photo' => 'Foto Produk',
            'social_media' => 'Social Media',
            'catalogue' => 'Katalog',
            'other' => 'Lainnya',
        ])
        ->and(RndProjectMarketingMaterial::PHYSICAL_TYPES)->toBe(['packaging_design', 'sticker']);

    expect(MarketingDesignItem::factory()->create(['type' => 'catalogue'])->typeLabel())->toBe('Katalog');
});
