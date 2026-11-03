<?php

use App\Actions\BrandMarketing\DesignMaster\ApproveDesignVersionAction;
use App\Actions\BrandMarketing\DesignMaster\ArchiveDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\CreateDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\PublishDesignVersionAction;
use App\Actions\BrandMarketing\DesignMaster\SaveDesignItemAction;
use App\Actions\BrandMarketing\DesignMaster\SubmitDesignVersionAction;
use App\Enums\MarketingDesignFileRole;
use App\Enums\MarketingDesignVersionStatus;
use App\Models\Brand;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignBundleVersion;
use App\Models\MarketingDesignFile;
use App\Models\MarketingDesignItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function designUser(string $profile): User
{
    $user = User::factory()->create(['is_active' => true]);

    match ($profile) {
        'designer' => $user->assignRole('DESIGN_STAFF'),
        'purchasing' => $user->assignRole('PURCHASING_STAFF'),
        'reviewer' => $user->givePermissionTo('view design master bundles', 'review design master bundles'),
        'publisher' => $user->givePermissionTo('view design master bundles', 'publish design master bundles', 'archive design master bundles'),
        'view_only' => $user->givePermissionTo('view design master bundles'),
        'superadmin' => $user->assignRole(Role::findOrCreate('SUPERADMIN', 'web')),
        'none' => null,
    };

    return $user;
}

function designVersionWith(MarketingDesignVersionStatus $status): MarketingDesignBundleVersion
{
    return MarketingDesignBundleVersion::factory()->status($status)->create();
}

it('registers every Master Design permission', function () {
    $registered = config('permissions.Brand Marketing.Master Design');

    expect($registered)->toContain(
        'view design master bundles', 'create design master bundles', 'edit design master bundles', 'upload design master files',
        'review design master bundles', 'publish design master bundles', 'archive design master bundles',
        'download design master production files', 'download design master source files',
    );

    foreach ($registered as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue();
    }

    expect(collect($registered)->filter(fn (string $permission): bool => str_starts_with($permission, 'delete')))->toBeEmpty();
});

it('applies the bundle ability matrix', function (string $profile, array $expected) {
    $user = designUser($profile);
    $bundle = MarketingDesignBundle::factory()->create();
    $gate = Gate::forUser($user);

    expect([
        'viewAny' => $gate->allows('viewAny', MarketingDesignBundle::class),
        'create' => $gate->allows('create', MarketingDesignBundle::class),
        'update' => $gate->allows('update', $bundle),
        'archive' => $gate->allows('archive', $bundle),
    ])->toBe($expected);
})->with([
    'designer' => ['designer', ['viewAny' => true, 'create' => true, 'update' => true, 'archive' => false]],
    'reviewer' => ['reviewer', ['viewAny' => true, 'create' => false, 'update' => false, 'archive' => false]],
    'publisher' => ['publisher', ['viewAny' => true, 'create' => false, 'update' => false, 'archive' => true]],
    'purchasing' => ['purchasing', ['viewAny' => true, 'create' => false, 'update' => false, 'archive' => false]],
    'no permission' => ['none', ['viewAny' => false, 'create' => false, 'update' => false, 'archive' => false]],
    'superadmin' => ['superadmin', ['viewAny' => true, 'create' => true, 'update' => true, 'archive' => true]],
]);

it('applies the version ability matrix', function (string $profile, MarketingDesignVersionStatus $status, string $ability, bool $allowed) {
    $version = designVersionWith($status);

    expect(Gate::forUser(designUser($profile))->allows($ability, $version))->toBe($allowed);
})->with([
    'designer edits draft' => ['designer', MarketingDesignVersionStatus::Draft, 'editContent', true],
    'designer uploads to draft' => ['designer', MarketingDesignVersionStatus::Draft, 'uploadFile', true],
    'designer submits draft' => ['designer', MarketingDesignVersionStatus::Draft, 'submit', true],
    'designer cannot edit review' => ['designer', MarketingDesignVersionStatus::InReview, 'editContent', false],
    'designer cannot approve' => ['designer', MarketingDesignVersionStatus::InReview, 'approve', false],
    'designer cannot publish' => ['designer', MarketingDesignVersionStatus::Approved, 'publish', false],
    'reviewer approves' => ['reviewer', MarketingDesignVersionStatus::InReview, 'approve', true],
    'reviewer returns review' => ['reviewer', MarketingDesignVersionStatus::InReview, 'requestRevision', true],
    'reviewer returns approved' => ['reviewer', MarketingDesignVersionStatus::Approved, 'requestRevision', true],
    'reviewer cannot publish' => ['reviewer', MarketingDesignVersionStatus::Approved, 'publish', false],
    'reviewer cannot upload' => ['reviewer', MarketingDesignVersionStatus::Draft, 'uploadFile', false],
    'publisher publishes' => ['publisher', MarketingDesignVersionStatus::Approved, 'publish', true],
    'publisher returns approved' => ['publisher', MarketingDesignVersionStatus::Approved, 'requestRevision', true],
    'publisher cannot return review' => ['publisher', MarketingDesignVersionStatus::InReview, 'requestRevision', false],
    'publisher cannot approve' => ['publisher', MarketingDesignVersionStatus::InReview, 'approve', false],
    'purchasing cannot edit' => ['purchasing', MarketingDesignVersionStatus::Draft, 'editContent', false],
    'purchasing cannot upload' => ['purchasing', MarketingDesignVersionStatus::Draft, 'uploadFile', false],
    'purchasing cannot approve' => ['purchasing', MarketingDesignVersionStatus::InReview, 'approve', false],
    'purchasing cannot publish' => ['purchasing', MarketingDesignVersionStatus::Approved, 'publish', false],
    'superadmin passes the gate' => ['superadmin', MarketingDesignVersionStatus::Approved, 'publish', true],
]);

it('shows purchasing only the published version of an active bundle', function () {
    $purchasing = designUser('purchasing');
    $gate = Gate::forUser($purchasing);

    foreach (MarketingDesignVersionStatus::cases() as $status) {
        $version = designVersionWith($status);
        expect($gate->allows('view', $version))->toBe($status === MarketingDesignVersionStatus::Published);
    }

    $archived = MarketingDesignBundleVersion::factory()->published()
        ->for(MarketingDesignBundle::factory()->archived(), 'bundle')
        ->create();

    expect($gate->allows('view', $archived))->toBeFalse()
        ->and($gate->allows('view', $archived->bundle))->toBeFalse();
});

it('scopes visible bundles for internal and non-internal users', function () {
    $published = MarketingDesignBundleVersion::factory()->published()->create()->bundle;
    $draftOnly = MarketingDesignBundleVersion::factory()->create()->bundle;
    $archived = MarketingDesignBundleVersion::factory()->published()->for(MarketingDesignBundle::factory()->archived(), 'bundle')->create()->bundle;

    expect(MarketingDesignBundle::query()->visibleTo(designUser('purchasing'))->pluck('id')->all())->toBe([$published->id])
        ->and(MarketingDesignBundle::query()->visibleTo(designUser('view_only'))->pluck('id')->all())->toBe([$published->id])
        ->and(MarketingDesignBundle::query()->visibleTo(designUser('designer'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$published->id, $draftOnly->id, $archived->id])->sort()->values()->all());
});

it('rejects mutation Actions on the server for users without permission', function () {
    $purchasing = designUser('purchasing');
    $draft = designVersionWith(MarketingDesignVersionStatus::Draft);

    expect(fn () => app(CreateDesignBundleAction::class)->execute($purchasing, ['brand_id' => Brand::factory()->create()->id, 'code' => 'A', 'name' => 'A', 'category' => 'other']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveDesignItemAction::class)->execute($purchasing, $draft, ['type' => 'other', 'title' => 'A']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SubmitDesignVersionAction::class)->execute($purchasing, $draft))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ApproveDesignVersionAction::class)->execute($purchasing, designVersionWith(MarketingDesignVersionStatus::InReview)))->toThrow(AuthorizationException::class)
        ->and(fn () => app(PublishDesignVersionAction::class)->execute($purchasing, designVersionWith(MarketingDesignVersionStatus::Approved)))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ArchiveDesignBundleAction::class)->execute($purchasing, $draft->bundle, 'alasan'))->toThrow(AuthorizationException::class);
});

it('applies the file access matrix per role', function (string $profile, MarketingDesignFileRole $role, bool $allowed) {
    $version = MarketingDesignBundleVersion::factory()->published()->create();
    $item = MarketingDesignItem::factory()->for($version, 'version')->create();
    $file = MarketingDesignFile::factory()->role($role)->for($item, 'item')->create();

    expect(Gate::forUser(designUser($profile))->allows('download', $file))->toBe($allowed);
})->with([
    'purchasing production' => ['purchasing', MarketingDesignFileRole::Production, true],
    'purchasing preview' => ['purchasing', MarketingDesignFileRole::Preview, true],
    'purchasing source' => ['purchasing', MarketingDesignFileRole::Source, false],
    'purchasing supporting' => ['purchasing', MarketingDesignFileRole::Supporting, false],
    'view only production' => ['view_only', MarketingDesignFileRole::Production, false],
    'view only source' => ['view_only', MarketingDesignFileRole::Source, false],
    'designer source' => ['designer', MarketingDesignFileRole::Source, true],
    'designer supporting' => ['designer', MarketingDesignFileRole::Supporting, true],
    'reviewer production without grant' => ['reviewer', MarketingDesignFileRole::Production, false],
    'reviewer supporting' => ['reviewer', MarketingDesignFileRole::Supporting, true],
    'no permission preview' => ['none', MarketingDesignFileRole::Preview, false],
    'superadmin source' => ['superadmin', MarketingDesignFileRole::Source, true],
]);

it('grants source only through the separate permission', function () {
    $user = designUser('purchasing');
    $version = MarketingDesignBundleVersion::factory()->published()->create();
    $item = MarketingDesignItem::factory()->for($version, 'version')->create();
    $source = MarketingDesignFile::factory()->role(MarketingDesignFileRole::Source)->for($item, 'item')->create();

    expect(Gate::forUser($user)->allows('download', $source))->toBeFalse();

    $user->givePermissionTo('download design master source files');

    expect(Gate::forUser($user->fresh())->allows('download', $source))->toBeTrue();
});

it('denies purchasing the production file of a superseded version', function () {
    $purchasing = designUser('purchasing');
    $version = designVersionWith(MarketingDesignVersionStatus::Superseded);
    $item = MarketingDesignItem::factory()->for($version, 'version')->create();
    $file = MarketingDesignFile::factory()->role(MarketingDesignFileRole::Production)->for($item, 'item')->create();

    expect(Gate::forUser($purchasing)->allows('download', $file))->toBeFalse()
        ->and(Gate::forUser(designUser('designer'))->allows('download', $file))->toBeTrue();
});
