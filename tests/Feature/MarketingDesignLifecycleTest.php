<?php

use App\Actions\BrandMarketing\DesignMaster\ApproveDesignVersionAction;
use App\Actions\BrandMarketing\DesignMaster\ArchiveDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\CreateDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\CreateDesignRevisionAction;
use App\Actions\BrandMarketing\DesignMaster\PublishDesignVersionAction;
use App\Actions\BrandMarketing\DesignMaster\RemoveDesignFileAction;
use App\Actions\BrandMarketing\DesignMaster\RemoveDesignItemAction;
use App\Actions\BrandMarketing\DesignMaster\ReopenDesignVersionAction;
use App\Actions\BrandMarketing\DesignMaster\RequestDesignVersionRevisionAction;
use App\Actions\BrandMarketing\DesignMaster\RestoreDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\SaveDesignItemAction;
use App\Actions\BrandMarketing\DesignMaster\SubmitDesignVersionAction;
use App\Actions\BrandMarketing\DesignMaster\UpdateDesignBundleAction;
use App\Enums\MarketingDesignAuditEvent;
use App\Enums\MarketingDesignBundleStatus;
use App\Enums\MarketingDesignFileRole;
use App\Enums\MarketingDesignVersionStatus;
use App\Models\Brand;
use App\Models\MarketingDesignAuditLog;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignBundleVersion;
use App\Models\MarketingDesignFile;
use App\Models\MarketingDesignItem;
use App\Models\MarketingMaterialFulfillment;
use App\Models\RndProject;
use App\Models\RndProjectMarketingMaterial;
use App\Models\RndProjectProduct;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actor = User::factory()->create(['is_active' => true]);
    $this->actor->assignRole(Role::findOrCreate('DESIGN_MASTER_FULL', 'web')->syncPermissions([
        'view design master bundles', 'create design master bundles', 'edit design master bundles', 'upload design master files',
        'review design master bundles', 'publish design master bundles', 'archive design master bundles',
    ]));
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

/** A draft whose single digital item passes the review guard. */
function readyDesignVersion(?MarketingDesignBundle $bundle = null, int $number = 1, MarketingDesignVersionStatus $status = MarketingDesignVersionStatus::Draft): MarketingDesignBundleVersion
{
    $version = MarketingDesignBundleVersion::factory()
        ->for($bundle ?? MarketingDesignBundle::factory(), 'bundle')
        ->status($status)
        ->create(['version_number' => $number]);
    $item = MarketingDesignItem::factory()->for($version, 'version')->create();
    MarketingDesignFile::factory()->for($item, 'item')->create();

    return $version;
}

it('creates a bundle under one brand with a Version 1 draft and audit', function () {
    $bundle = app(CreateDesignBundleAction::class)->execute($this->actor, designBundleData());

    expect($bundle->code)->toBe('lebaran 2027')
        ->and($bundle->normalized_code)->toBe('LEBARAN-2027')
        ->and($bundle->status)->toBe(MarketingDesignBundleStatus::Active)
        ->and($bundle->versions()->sole()->version_number)->toBe(1)
        ->and($bundle->versions()->sole()->status)->toBe(MarketingDesignVersionStatus::Draft)
        ->and($bundle->auditLogs()->pluck('event')->all())->toContain(MarketingDesignAuditEvent::BundleCreated, MarketingDesignAuditEvent::VersionCreated);
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

it('keeps the brand fixed when a bundle is edited', function () {
    $bundle = app(CreateDesignBundleAction::class)->execute($this->actor, designBundleData());
    $otherBrand = Brand::factory()->create();

    $updated = app(UpdateDesignBundleAction::class)->execute($this->actor, $bundle, designBundleData(['brand_id' => $otherBrand->id, 'name' => 'Nama Baru']));

    expect($updated->brand_id)->toBe($this->brand->id)->and($updated->name)->toBe('Nama Baru');
});

it('enforces code uniqueness in the database', function () {
    MarketingDesignBundle::factory()->for($this->brand)->create(['normalized_code' => 'SAME']);

    expect(fn () => MarketingDesignBundle::factory()->for($this->brand)->create(['normalized_code' => 'SAME']))
        ->toThrow(QueryException::class);
});

it('walks draft, review, revision, approval, publish, revision two, and supersede', function () {
    $v1 = readyDesignVersion();

    $v1 = app(SubmitDesignVersionAction::class)->execute($this->actor, $v1);
    expect($v1->status)->toBe(MarketingDesignVersionStatus::InReview)->and($v1->submitted_by)->toBe($this->actor->id);

    $v1 = app(RequestDesignVersionRevisionAction::class)->execute($this->actor, $v1, 'Warna belum sesuai');
    expect($v1->status)->toBe(MarketingDesignVersionStatus::RevisionRequired)->and($v1->review_note)->toBe('Warna belum sesuai');

    $v1 = app(ReopenDesignVersionAction::class)->execute($this->actor, $v1);
    expect($v1->status)->toBe(MarketingDesignVersionStatus::Draft);

    $v1 = app(SubmitDesignVersionAction::class)->execute($this->actor, $v1);
    $v1 = app(ApproveDesignVersionAction::class)->execute($this->actor, $v1, 'OK');
    expect($v1->status)->toBe(MarketingDesignVersionStatus::Approved);

    $v1 = app(PublishDesignVersionAction::class)->execute($this->actor, $v1, 'Rilis');
    expect($v1->status)->toBe(MarketingDesignVersionStatus::Published)->and($v1->published_marker)->toBe(1);

    $v2 = app(CreateDesignRevisionAction::class)->execute($this->actor, $v1->bundle, 'Ganti warna');
    expect($v2->version_number)->toBe(2)->and($v2->status)->toBe(MarketingDesignVersionStatus::Draft);

    $v2 = app(SubmitDesignVersionAction::class)->execute($this->actor, $v2);
    $v2 = app(ApproveDesignVersionAction::class)->execute($this->actor, $v2);
    $v2 = app(PublishDesignVersionAction::class)->execute($this->actor, $v2);

    expect($v1->fresh()->status)->toBe(MarketingDesignVersionStatus::Superseded)
        ->and($v1->fresh()->published_marker)->toBeNull()
        ->and($v1->fresh()->superseded_at)->not->toBeNull()
        ->and($v2->fresh()->status)->toBe(MarketingDesignVersionStatus::Published)
        ->and($v1->bundle->publishedVersion()->sole()->id)->toBe($v2->id)
        ->and(MarketingDesignAuditLog::query()->where('event', MarketingDesignAuditEvent::VersionSuperseded)->sole()->marketing_design_bundle_version_id)->toBe($v1->id);
});

it('rejects transitions from the wrong status', function (MarketingDesignVersionStatus $status, string $action) {
    $version = readyDesignVersion(status: $status);

    expect(fn () => match ($action) {
        'submit' => app(SubmitDesignVersionAction::class)->execute($this->actor, $version),
        'approve' => app(ApproveDesignVersionAction::class)->execute($this->actor, $version),
        'publish' => app(PublishDesignVersionAction::class)->execute($this->actor, $version),
        'reopen' => app(ReopenDesignVersionAction::class)->execute($this->actor, $version),
        'revision' => app(RequestDesignVersionRevisionAction::class)->execute($this->actor, $version, 'alasan'),
    })->toThrow(AuthorizationException::class);

    expect($version->fresh()->status)->toBe($status);
})->with([
    'submit from review' => [MarketingDesignVersionStatus::InReview, 'submit'],
    'approve from draft' => [MarketingDesignVersionStatus::Draft, 'approve'],
    'publish from draft' => [MarketingDesignVersionStatus::Draft, 'publish'],
    'publish from review' => [MarketingDesignVersionStatus::InReview, 'publish'],
    'reopen from approved' => [MarketingDesignVersionStatus::Approved, 'reopen'],
    'revision from draft' => [MarketingDesignVersionStatus::Draft, 'revision'],
]);

it('binds SUPERADMIN to the lifecycle too, because Gate::before must not skip status rules', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole(Role::findOrCreate('SUPERADMIN', 'web'));

    $draft = readyDesignVersion();
    $published = readyDesignVersion(status: MarketingDesignVersionStatus::Published);
    $archivedBundle = MarketingDesignBundle::factory()->archived()->create();

    expect(fn () => app(PublishDesignVersionAction::class)->execute($admin, $draft))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ApproveDesignVersionAction::class)->execute($admin, $draft))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveDesignItemAction::class)->execute($admin, $published, ['type' => 'other', 'title' => 'X']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RestoreDesignBundleAction::class)->execute($admin, $draft->bundle))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ArchiveDesignBundleAction::class)->execute($admin, $archivedBundle, 'lagi'))->toThrow(AuthorizationException::class);

    expect($draft->fresh()->status)->toBe(MarketingDesignVersionStatus::Draft)
        ->and(app(SubmitDesignVersionAction::class)->execute($admin, $draft)->status)->toBe(MarketingDesignVersionStatus::InReview);
});

it('requires a reason to return a version or archive a bundle', function () {
    $version = readyDesignVersion(status: MarketingDesignVersionStatus::InReview);

    expect(fn () => app(RequestDesignVersionRevisionAction::class)->execute($this->actor, $version, '  '))->toThrow(ValidationException::class)
        ->and(fn () => app(ArchiveDesignBundleAction::class)->execute($this->actor, $version->bundle, ''))->toThrow(ValidationException::class);
});

describe('readiness guard', function () {
    it('allows an incomplete draft but blocks its submit', function () {
        $version = MarketingDesignBundleVersion::factory()->create();

        expect(fn () => app(SubmitDesignVersionAction::class)->execute($this->actor, $version))
            ->toThrow(ValidationException::class, 'belum mempunyai item');

        app(SaveDesignItemAction::class)->execute($this->actor, $version, ['type' => 'sticker', 'title' => 'Sticker Tutup']);

        expect($version->items()->count())->toBe(1);

        try {
            app(SubmitDesignVersionAction::class)->execute($this->actor, $version);
            $this->fail('Submit should be blocked');
        } catch (ValidationException $exception) {
            expect($exception->getMessage())->toContain('File preview belum ada', 'Spesifikasi dimensi dan material', 'File production belum ada');
        }

        expect($version->fresh()->status)->toBe(MarketingDesignVersionStatus::Draft);
    });

    it('re-checks the guard on publish', function () {
        $version = MarketingDesignBundleVersion::factory()->status(MarketingDesignVersionStatus::Approved)->create();
        MarketingDesignItem::factory()->physical()->for($version, 'version')->create();

        expect(fn () => app(PublishDesignVersionAction::class)->execute($this->actor, $version))
            ->toThrow(ValidationException::class, 'File preview belum ada');

        expect($version->fresh()->status)->toBe(MarketingDesignVersionStatus::Approved);
    });

    it('ignores a browser attempt to mark a physical type as digital', function () {
        $version = MarketingDesignBundleVersion::factory()->create();
        $item = app(SaveDesignItemAction::class)->execute($this->actor, $version, ['type' => 'packaging_design', 'title' => 'Box', 'is_physical' => false]);

        expect($item->isPhysical())->toBeTrue();
    });

    it('passes a complete physical item', function () {
        $version = MarketingDesignBundleVersion::factory()->create();
        $item = MarketingDesignItem::factory()->physical()->for($version, 'version')->create();
        MarketingDesignFile::factory()->for($item, 'item')->create();
        MarketingDesignFile::factory()->role(MarketingDesignFileRole::Production)->for($item, 'item')->create();

        expect(app(SubmitDesignVersionAction::class)->execute($this->actor, $version)->status)->toBe(MarketingDesignVersionStatus::InReview);
    });
});

describe('immutability and concurrency', function () {
    it('refuses edits, item changes, file changes, and deletion on a published version', function () {
        $version = readyDesignVersion(status: MarketingDesignVersionStatus::Published);
        $item = $version->items()->sole();
        $file = $item->files()->sole();

        expect(fn () => app(SaveDesignItemAction::class)->execute($this->actor, $version, ['type' => 'other', 'title' => 'X']))->toThrow(AuthorizationException::class)
            ->and(fn () => app(RemoveDesignItemAction::class)->execute($this->actor, $item))->toThrow(AuthorizationException::class)
            ->and(fn () => app(RemoveDesignFileAction::class)->execute($this->actor, $file))->toThrow(AuthorizationException::class)
            ->and(fn () => $version->update(['change_summary' => 'x']))->toThrow(LogicException::class)
            ->and(fn () => $item->update(['title' => 'x']))->toThrow(LogicException::class)
            ->and(fn () => $file->update(['original_name' => 'x']))->toThrow(LogicException::class)
            ->and(fn () => $version->delete())->toThrow(LogicException::class)
            ->and(fn () => $version->bundle->delete())->toThrow(LogicException::class)
            ->and(fn () => $item->forceDelete())->toThrow(LogicException::class)
            ->and(fn () => $file->forceDelete())->toThrow(LogicException::class);
    });

    it('never lets a superseded version change again', function () {
        $version = readyDesignVersion(status: MarketingDesignVersionStatus::Superseded);

        expect(fn () => $version->update(['status' => MarketingDesignVersionStatus::Published]))->toThrow(LogicException::class);
    });

    it('makes a repeated publish a refused no-op', function () {
        $version = readyDesignVersion(status: MarketingDesignVersionStatus::Approved);
        app(PublishDesignVersionAction::class)->execute($this->actor, $version);

        expect(fn () => app(PublishDesignVersionAction::class)->execute($this->actor, $version->fresh()))->toThrow(AuthorizationException::class);

        expect(MarketingDesignAuditLog::query()->where('event', MarketingDesignAuditEvent::VersionPublished)->count())->toBe(1);
    });

    it('publishes the second of two approved versions and supersedes the first', function () {
        $bundle = MarketingDesignBundle::factory()->create();
        $first = readyDesignVersion($bundle, 1, MarketingDesignVersionStatus::Approved);
        $second = readyDesignVersion($bundle, 2, MarketingDesignVersionStatus::Approved);

        app(PublishDesignVersionAction::class)->execute($this->actor, $first);
        app(PublishDesignVersionAction::class)->execute($this->actor, $second);

        expect($bundle->versions()->where('status', MarketingDesignVersionStatus::Published)->sole()->id)->toBe($second->id)
            ->and($first->fresh()->status)->toBe(MarketingDesignVersionStatus::Superseded);
    });

    it('rejects two published versions in the database', function () {
        $bundle = MarketingDesignBundle::factory()->create();
        MarketingDesignBundleVersion::factory()->published()->for($bundle, 'bundle')->create(['version_number' => 1]);

        expect(fn () => MarketingDesignBundleVersion::factory()->published()->for($bundle, 'bundle')->create(['version_number' => 2]))
            ->toThrow(QueryException::class);
    });

    it('rejects a duplicate version number in the database', function () {
        $bundle = MarketingDesignBundle::factory()->create();
        MarketingDesignBundleVersion::factory()->for($bundle, 'bundle')->create(['version_number' => 1]);

        expect(fn () => MarketingDesignBundleVersion::factory()->for($bundle, 'bundle')->create(['version_number' => 1]))
            ->toThrow(QueryException::class);
    });

    it('rolls back the supersede when publishing the new version fails', function () {
        $bundle = MarketingDesignBundle::factory()->create();
        $current = readyDesignVersion($bundle, 1, MarketingDesignVersionStatus::Published);
        $current->forceFill(['published_marker' => 1])->saveQuietly();
        $next = readyDesignVersion($bundle, 2, MarketingDesignVersionStatus::Approved);

        MarketingDesignAuditLog::creating(function (MarketingDesignAuditLog $log): void {
            if ($log->event === MarketingDesignAuditEvent::VersionPublished) {
                throw new RuntimeException('boom');
            }
        });

        expect(fn () => app(PublishDesignVersionAction::class)->execute($this->actor, $next))->toThrow(RuntimeException::class);

        expect($current->fresh()->status)->toBe(MarketingDesignVersionStatus::Published)
            ->and($next->fresh()->status)->toBe(MarketingDesignVersionStatus::Approved);
    });
});

describe('revision', function () {
    it('clones item metadata and references the same object without copying', function () {
        $published = MarketingDesignBundleVersion::factory()->published()->create();
        $item = MarketingDesignItem::factory()->physical()->for($published, 'version')->create();
        $file = MarketingDesignFile::factory()->role(MarketingDesignFileRole::Production)->for($item, 'item')->create();

        $revision = app(CreateDesignRevisionAction::class)->execute($this->actor, $published->bundle, 'Perubahan');
        $clone = $revision->items()->sole();
        $reused = $clone->files()->sole();

        expect($revision->cloned_from_version_id)->toBe($published->id)
            ->and($clone->id)->not->toBe($item->id)
            ->and($clone->cloned_from_item_id)->toBe($item->id)
            ->and($clone->dimensions)->toBe($item->dimensions)
            ->and($reused->reused_from_file_id)->toBe($file->id)
            ->and($reused->path)->toBe($file->path)
            ->and($reused->checksum_sha256)->toBe($file->checksum_sha256);
    });

    it('needs a published version and no other open version', function () {
        $draftOnly = MarketingDesignBundleVersion::factory()->create();
        expect(fn () => app(CreateDesignRevisionAction::class)->execute($this->actor, $draftOnly->bundle, 'x'))
            ->toThrow(ValidationException::class, 'version published');

        $published = MarketingDesignBundleVersion::factory()->published()->create();
        app(CreateDesignRevisionAction::class)->execute($this->actor, $published->bundle, 'Pertama');

        expect(fn () => app(CreateDesignRevisionAction::class)->execute($this->actor, $published->bundle, 'Kedua'))
            ->toThrow(ValidationException::class, 'masih berjalan');
    });

    it('requires a change summary', function () {
        $published = MarketingDesignBundleVersion::factory()->published()->create();

        expect(fn () => app(CreateDesignRevisionAction::class)->execute($this->actor, $published->bundle, ' '))->toThrow(ValidationException::class);
    });
});

describe('archive', function () {
    it('keeps every version, item, file, and audit, and blocks new work until restored', function () {
        $version = readyDesignVersion(status: MarketingDesignVersionStatus::Published);
        $version->forceFill(['published_marker' => 1])->saveQuietly();
        $bundle = $version->bundle;
        $draft = readyDesignVersion($bundle, 2);

        app(ArchiveDesignBundleAction::class)->execute($this->actor, $bundle, 'Campaign selesai');

        expect($bundle->fresh()->status)->toBe(MarketingDesignBundleStatus::Archived)
            ->and($bundle->fresh()->archive_reason)->toBe('Campaign selesai')
            ->and(MarketingDesignBundleVersion::count())->toBe(2)
            ->and(MarketingDesignItem::count())->toBe(2)
            ->and(MarketingDesignFile::count())->toBe(2)
            ->and($version->fresh()->status)->toBe(MarketingDesignVersionStatus::Published);

        $fresh = $bundle->fresh();
        expect(fn () => app(CreateDesignRevisionAction::class)->execute($this->actor, $fresh, 'x'))->toThrow(AuthorizationException::class)
            ->and(fn () => app(SubmitDesignVersionAction::class)->execute($this->actor, $draft))->toThrow(AuthorizationException::class)
            ->and(fn () => app(ArchiveDesignBundleAction::class)->execute($this->actor, $fresh, 'lagi'))->toThrow(AuthorizationException::class);

        app(RestoreDesignBundleAction::class)->execute($this->actor, $fresh);

        expect($bundle->fresh()->status)->toBe(MarketingDesignBundleStatus::Active)
            ->and($bundle->fresh()->archived_at)->toBeNull()
            ->and(app(SubmitDesignVersionAction::class)->execute($this->actor, $draft->fresh())->status)->toBe(MarketingDesignVersionStatus::InReview);

        expect($bundle->auditLogs()->pluck('event')->all())->toContain(MarketingDesignAuditEvent::BundleArchived, MarketingDesignAuditEvent::BundleRestored);
    });
});

it('keeps the audit log append-only', function () {
    $bundle = app(CreateDesignBundleAction::class)->execute($this->actor, designBundleData());
    $log = $bundle->auditLogs()->first();

    expect(fn () => $log->update(['reason' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('notifies reviewers on submit, the creator on revision, and purchasing on publish', function () {
    $reviewer = User::factory()->create(['is_active' => true]);
    $reviewer->givePermissionTo('review design master bundles');
    $creator = User::factory()->create(['is_active' => true]);
    $purchasing = User::factory()->create(['is_active' => true]);
    $purchasing->assignRole('PURCHASING_STAFF');

    $version = readyDesignVersion();
    $version->update(['created_by' => $creator->id]);

    app(SubmitDesignVersionAction::class)->execute($this->actor, $version);
    expect($reviewer->notifications()->count())->toBe(1);

    app(RequestDesignVersionRevisionAction::class)->execute($this->actor, $version->fresh(), 'perbaiki');
    expect($creator->notifications()->count())->toBe(1);

    app(ReopenDesignVersionAction::class)->execute($this->actor, $version->fresh());
    app(SubmitDesignVersionAction::class)->execute($this->actor, $version->fresh());
    app(ApproveDesignVersionAction::class)->execute($this->actor, $version->fresh());
    app(PublishDesignVersionAction::class)->execute($this->actor, $version->fresh());

    expect($purchasing->notifications()->count())->toBe(1);
});

it('leaves Project R&D marketing materials and fulfillment untouched and reuses their vocabulary', function () {
    $project = RndProject::create(['name' => 'Project A', 'start_date' => now(), 'end_date' => now()->addMonth()]);
    $product = RndProjectProduct::create(['rnd_project_id' => $project->id, 'name' => 'Produk A']);
    $material = RndProjectMarketingMaterial::create(['rnd_project_product_id' => $product->id, 'type' => 'packaging_design', 'title' => 'Kemasan', 'file_path' => 'rnd/marketing-materials/1/1/a.pdf', 'original_name' => 'a.pdf']);
    $before = $material->fresh()->getAttributes();

    $version = readyDesignVersion(status: MarketingDesignVersionStatus::Approved);
    app(PublishDesignVersionAction::class)->execute($this->actor, $version);
    app(ArchiveDesignBundleAction::class)->execute($this->actor, $version->bundle, 'selesai');

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

    $item = MarketingDesignItem::factory()->create(['type' => 'catalogue']);
    expect($item->typeLabel())->toBe('Katalog')->and($item->isPhysical())->toBeFalse();
});
