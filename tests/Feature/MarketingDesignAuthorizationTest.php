<?php

use App\Actions\BrandMarketing\DesignMaster\ArchiveDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\CreateDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\SaveDesignItemAction;
use App\Actions\BrandMarketing\DesignMaster\UpdateDesignBundleAction;
use App\Models\Brand;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignFile;
use App\Models\MarketingDesignItem;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
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
        'archiver' => $user->givePermissionTo('view design master bundles', 'archive design master bundles'),
        'view_only' => $user->givePermissionTo('view design master bundles'),
        'superadmin' => $user->assignRole(Role::findOrCreate('SUPERADMIN', 'web')),
        'none' => null,
    };

    return $user;
}

it('registers the Master Design permissions with a single download permission', function () {
    $registered = config('permissions.Brand Marketing.Master Design');

    expect($registered)->toBe([
        'view design master bundles',
        'create design master bundles',
        'edit design master bundles',
        'upload design master files',
        'archive design master bundles',
        'download design master files',
    ]);

    foreach ($registered as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue();
    }
});

it('applies the bundle ability matrix', function (string $profile, bool $archived, array $expected) {
    $bundle = $archived ? MarketingDesignBundle::factory()->archived()->create() : MarketingDesignBundle::factory()->create();
    $user = designUser($profile);
    $access = app(MarketingDesignAccess::class);

    expect([
        'viewAny' => Gate::forUser($user)->allows('viewAny', MarketingDesignBundle::class),
        'create' => Gate::forUser($user)->allows('create', MarketingDesignBundle::class),
        'view' => $access->allows($user, 'view', $bundle),
        'update' => $access->allows($user, 'update', $bundle),
        'uploadFile' => $access->allows($user, 'uploadFile', $bundle),
        'archive' => $access->allows($user, 'archive', $bundle),
        'restore' => $access->allows($user, 'restore', $bundle),
    ])->toBe($expected);
})->with([
    'designer active' => ['designer', false, ['viewAny' => true, 'create' => true, 'view' => true, 'update' => true, 'uploadFile' => true, 'archive' => false, 'restore' => false]],
    'designer archived' => ['designer', true, ['viewAny' => true, 'create' => true, 'view' => true, 'update' => false, 'uploadFile' => false, 'archive' => false, 'restore' => false]],
    'archiver active' => ['archiver', false, ['viewAny' => true, 'create' => false, 'view' => true, 'update' => false, 'uploadFile' => false, 'archive' => true, 'restore' => false]],
    'archiver archived' => ['archiver', true, ['viewAny' => true, 'create' => false, 'view' => true, 'update' => false, 'uploadFile' => false, 'archive' => false, 'restore' => true]],
    'purchasing active' => ['purchasing', false, ['viewAny' => true, 'create' => false, 'view' => true, 'update' => false, 'uploadFile' => false, 'archive' => false, 'restore' => false]],
    'purchasing archived' => ['purchasing', true, ['viewAny' => true, 'create' => false, 'view' => false, 'update' => false, 'uploadFile' => false, 'archive' => false, 'restore' => false]],
    'no permission' => ['none', false, ['viewAny' => false, 'create' => false, 'view' => false, 'update' => false, 'uploadFile' => false, 'archive' => false, 'restore' => false]],
    'superadmin active' => ['superadmin', false, ['viewAny' => true, 'create' => true, 'view' => true, 'update' => true, 'uploadFile' => true, 'archive' => true, 'restore' => false]],
    'superadmin archived' => ['superadmin', true, ['viewAny' => true, 'create' => true, 'view' => true, 'update' => false, 'uploadFile' => false, 'archive' => false, 'restore' => true]],
]);

it('scopes visible bundles for internal and non-internal users', function () {
    $active = MarketingDesignBundle::factory()->create();
    $archived = MarketingDesignBundle::factory()->archived()->create();

    expect(MarketingDesignBundle::query()->visibleTo(designUser('purchasing'))->pluck('id')->all())->toBe([$active->id])
        ->and(MarketingDesignBundle::query()->visibleTo(designUser('view_only'))->pluck('id')->all())->toBe([$active->id])
        ->and(MarketingDesignBundle::query()->visibleTo(designUser('designer'))->pluck('id')->sort()->values()->all())->toBe([$active->id, $archived->id]);
});

it('rejects mutation Actions on the server for purchasing', function () {
    $purchasing = designUser('purchasing');
    $bundle = MarketingDesignBundle::factory()->create();

    expect(fn () => app(CreateDesignBundleAction::class)->execute($purchasing, ['brand_id' => Brand::factory()->create()->id, 'code' => 'A', 'name' => 'A', 'category' => 'other']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateDesignBundleAction::class)->execute($purchasing, $bundle, ['brand_id' => $bundle->brand_id, 'code' => 'A', 'name' => 'A', 'category' => 'other']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveDesignItemAction::class)->create($purchasing, $bundle, ['type' => 'other', 'title' => 'A'], null))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ArchiveDesignBundleAction::class)->execute($purchasing, $bundle, 'alasan'))->toThrow(AuthorizationException::class);
});

it('applies the file access matrix', function (string $profile, bool $allowed) {
    $file = MarketingDesignFile::factory()->create();

    expect(app(MarketingDesignAccess::class)->allows(designUser($profile), 'download', $file))->toBe($allowed);
})->with([
    'purchasing' => ['purchasing', true],
    'designer' => ['designer', true],
    'view only' => ['view_only', false],
    'archiver without grant' => ['archiver', false],
    'no permission' => ['none', false],
    'superadmin' => ['superadmin', true],
]);

it('denies purchasing the files of an archived bundle', function () {
    $item = MarketingDesignItem::factory()->for(MarketingDesignBundle::factory()->archived(), 'bundle')->create();
    $file = MarketingDesignFile::factory()->for($item, 'item')->create();

    expect(app(MarketingDesignAccess::class)->allows(designUser('purchasing'), 'download', $file))->toBeFalse()
        ->and(app(MarketingDesignAccess::class)->allows(designUser('designer'), 'download', $file))->toBeTrue();
});
