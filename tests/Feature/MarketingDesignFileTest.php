<?php

use App\Actions\BrandMarketing\DesignMaster\ArchiveDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\PublishDesignVersionAction;
use App\Actions\BrandMarketing\DesignMaster\RemoveDesignFileAction;
use App\Actions\BrandMarketing\DesignMaster\RemoveDesignItemAction;
use App\Actions\BrandMarketing\DesignMaster\UploadDesignFileAction;
use App\Enums\MarketingDesignAuditEvent;
use App\Enums\MarketingDesignFileRole;
use App\Enums\MarketingDesignVersionStatus;
use App\Models\MarketingDesignAuditLog;
use App\Models\MarketingDesignBundleVersion;
use App\Models\MarketingDesignFile;
use App\Models\MarketingDesignItem;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignFileStore;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('b2');
    Storage::disk('b2')->buildTemporaryUrlsUsing(fn (string $path, DateTimeInterface $expiration, array $options): string => 'https://signed.test/'.$path.'?disposition='.urlencode($options['ResponseContentDisposition']).'&expires='.$expiration->getTimestamp());

    $this->designer = User::factory()->create(['is_active' => true]);
    $this->designer->assignRole('DESIGN_STAFF');
    $this->purchasing = User::factory()->create(['is_active' => true]);
    $this->purchasing->assignRole('PURCHASING_STAFF');

    $this->version = MarketingDesignBundleVersion::factory()->create();
    $this->item = MarketingDesignItem::factory()->for($this->version, 'version')->create();
});

function designPdf(string $name = 'desain final.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 100, 'application/pdf');
}

function publishedDesignFile(MarketingDesignFileRole $role): MarketingDesignFile
{
    $version = MarketingDesignBundleVersion::factory()->published()->create();
    $item = MarketingDesignItem::factory()->for($version, 'version')->create();
    $file = MarketingDesignFile::factory()->role($role)->for($item, 'item')->create();
    Storage::disk('b2')->put($file->path, 'binary');

    return $file;
}

it('stores a private object under an ID path with metadata and checksum', function () {
    $upload = designPdf();

    $file = app(UploadDesignFileAction::class)->execute($this->designer, $this->item, MarketingDesignFileRole::Production, $upload);
    $bundle = $this->version->bundle;

    expect($file->path)->toMatch('#^brand-marketing/design-master/brand-'.$bundle->brand_id.'/bundle-'.$bundle->id.'/version-'.$this->version->id.'/item-'.$this->item->id.'/production/[0-9a-f-]{36}\.pdf$#')
        ->and($file->path)->not->toContain('desain')
        ->and($file->disk)->toBe('b2')
        ->and($file->original_name)->toBe('desain final.pdf')
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->extension)->toBe('pdf')
        ->and($file->size_bytes)->toBe($upload->getSize())
        ->and($file->checksum_sha256)->toBe(hash_file('sha256', $upload->getRealPath()))
        ->and($file->uploaded_by)->toBe($this->designer->id);

    Storage::disk('b2')->assertExists($file->path);
    expect(MarketingDesignAuditLog::query()->where('event', MarketingDesignAuditEvent::FileUploaded)->sole()->metadata)->toMatchArray(['role' => 'production']);
});

it('never reuses a path for two uploads', function () {
    $upload = app(UploadDesignFileAction::class);

    expect($upload->execute($this->designer, $this->item, MarketingDesignFileRole::Production, designPdf())->path)
        ->not->toBe($upload->execute($this->designer, $this->item, MarketingDesignFileRole::Production, designPdf())->path);
});

it('accepts and rejects formats per role', function (MarketingDesignFileRole $role, string $name, string $mime, bool $accepted) {
    $file = UploadedFile::fake()->create($name, 10, $mime);
    $action = fn () => app(UploadDesignFileAction::class)->execute($this->designer, $this->item, $role, $file);

    if ($accepted) {
        expect($action()->role)->toBe($role);

        return;
    }

    expect($action)->toThrow(ValidationException::class);
    expect(Storage::disk('b2')->allFiles())->toBeEmpty()
        ->and(MarketingDesignFile::count())->toBe(0);
})->with([
    'preview jpg' => [MarketingDesignFileRole::Preview, 'a.jpg', 'image/jpeg', true],
    'preview webp' => [MarketingDesignFileRole::Preview, 'a.webp', 'image/webp', true],
    'preview pdf' => [MarketingDesignFileRole::Preview, 'a.pdf', 'application/pdf', true],
    'preview svg' => [MarketingDesignFileRole::Preview, 'a.svg', 'image/svg+xml', false],
    'preview zip' => [MarketingDesignFileRole::Preview, 'a.zip', 'application/zip', false],
    'production pdf' => [MarketingDesignFileRole::Production, 'a.pdf', 'application/pdf', true],
    'production zip' => [MarketingDesignFileRole::Production, 'a.zip', 'application/zip', true],
    'production png' => [MarketingDesignFileRole::Production, 'a.png', 'image/png', false],
    'source zip' => [MarketingDesignFileRole::Source, 'a.zip', 'application/zip', true],
    'source pdf' => [MarketingDesignFileRole::Source, 'a.pdf', 'application/pdf', false],
    'source psd' => [MarketingDesignFileRole::Source, 'a.psd', 'image/vnd.adobe.photoshop', false],
    'supporting zip' => [MarketingDesignFileRole::Supporting, 'a.zip', 'application/zip', true],
    'supporting exe' => [MarketingDesignFileRole::Supporting, 'a.exe', 'application/x-msdownload', false],
    'renamed executable' => [MarketingDesignFileRole::Production, 'a.pdf', 'application/x-msdownload', false],
]);

it('enforces the size limit shared with the Livewire upload rule', function () {
    $limit = MarketingDesignFileRole::MAX_KILOBYTES;
    expect($limit)->toBe(12288);

    $tooBig = UploadedFile::fake()->create('a.pdf', $limit + 1, 'application/pdf');

    expect(fn () => app(UploadDesignFileAction::class)->execute($this->designer, $this->item, MarketingDesignFileRole::Production, $tooBig))
        ->toThrow(ValidationException::class, 'melebihi batas');

    expect(app(UploadDesignFileAction::class)->execute($this->designer, $this->item, MarketingDesignFileRole::Production, UploadedFile::fake()->create('b.pdf', $limit, 'application/pdf')))->toBeInstanceOf(MarketingDesignFile::class);
});

it('removes the new object when the database write fails', function () {
    MarketingDesignFile::creating(fn () => throw new RuntimeException('database down'));

    expect(fn () => app(UploadDesignFileAction::class)->execute($this->designer, $this->item, MarketingDesignFileRole::Production, designPdf()))
        ->toThrow(RuntimeException::class, 'database down');

    expect(Storage::disk('b2')->allFiles())->toBeEmpty()
        ->and(MarketingDesignFile::count())->toBe(0);
});

it('removes the new object when the version stopped being a draft during the upload', function () {
    $version = $this->version;
    $item = $this->item;
    $item->setRelation('version', $version);
    Storage::disk('b2');
    $store = Mockery::mock(MarketingDesignFileStore::class)->makePartial();
    $store->shouldReceive('store')->once()->andReturnUsing(function () use ($version): array {
        $version->update(['status' => MarketingDesignVersionStatus::InReview]);
        Storage::disk('b2')->put('brand-marketing/design-master/orphan.pdf', 'x');

        return ['disk' => 'b2', 'path' => 'brand-marketing/design-master/orphan.pdf', 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 1, 'checksum_sha256' => str_repeat('b', 64)];
    });
    app()->instance(MarketingDesignFileStore::class, $store);

    expect(fn () => app(UploadDesignFileAction::class)->execute($this->designer, $item, MarketingDesignFileRole::Production, designPdf()))
        ->toThrow(AuthorizationException::class);

    Storage::disk('b2')->assertMissing('brand-marketing/design-master/orphan.pdf');
    expect(MarketingDesignFile::count())->toBe(0);
});

it('reports a storage failure without leaving a record', function () {
    Storage::shouldReceive('disk')->with('b2')->andReturn(new class
    {
        public function putFileAs(): bool
        {
            return false;
        }
    });

    expect(fn () => app(UploadDesignFileAction::class)->execute($this->designer, $this->item, MarketingDesignFileRole::Production, designPdf()))
        ->toThrow(ValidationException::class, 'gagal disimpan');

    expect(MarketingDesignFile::count())->toBe(0);
});

it('replaces a draft file with a new record and path while keeping the old object', function () {
    $upload = app(UploadDesignFileAction::class);
    $old = $upload->execute($this->designer, $this->item, MarketingDesignFileRole::Production, designPdf('lama.pdf'));
    $new = $upload->execute($this->designer, $this->item, MarketingDesignFileRole::Production, designPdf('baru.pdf'), $old);

    expect($new->path)->not->toBe($old->path)
        ->and($old->fresh()->trashed())->toBeTrue()
        ->and($this->item->files()->pluck('id')->all())->toBe([$new->id]);

    Storage::disk('b2')->assertExists($old->path);
    Storage::disk('b2')->assertExists($new->path);
    expect(MarketingDesignAuditLog::query()->where('event', MarketingDesignAuditEvent::FileReplaced)->sole()->metadata['replaced_file_id'])->toBe($old->id);
});

it('refuses a replacement across items or roles', function () {
    $file = app(UploadDesignFileAction::class)->execute($this->designer, $this->item, MarketingDesignFileRole::Production, designPdf());
    $otherItem = MarketingDesignItem::factory()->for($this->version, 'version')->create();

    expect(fn () => app(UploadDesignFileAction::class)->execute($this->designer, $otherItem, MarketingDesignFileRole::Production, designPdf(), $file))->toThrow(ValidationException::class)
        ->and(fn () => app(UploadDesignFileAction::class)->execute($this->designer, $this->item, MarketingDesignFileRole::Preview, designPdf(), $file))->toThrow(ValidationException::class);
});

it('keeps objects when a file or item is removed from a draft', function () {
    $upload = app(UploadDesignFileAction::class);
    $file = $upload->execute($this->designer, $this->item, MarketingDesignFileRole::Production, designPdf());
    $other = $upload->execute($this->designer, $this->item, MarketingDesignFileRole::Preview, designPdf());

    app(RemoveDesignFileAction::class)->execute($this->designer, $file);
    app(RemoveDesignItemAction::class)->execute($this->designer, $this->item->fresh());

    expect($file->fresh()->trashed())->toBeTrue()
        ->and($this->item->fresh()->trashed())->toBeTrue();

    Storage::disk('b2')->assertExists($file->path);
    Storage::disk('b2')->assertExists($other->path);
});

it('keeps objects when publishing and archiving', function () {
    $file = app(UploadDesignFileAction::class)->execute($this->designer, $this->item, MarketingDesignFileRole::Preview, designPdf());
    $publisher = User::factory()->create(['is_active' => true]);
    $publisher->givePermissionTo('view design master bundles', 'publish design master bundles', 'archive design master bundles');

    $this->version->update(['status' => MarketingDesignVersionStatus::Approved]);
    app(PublishDesignVersionAction::class)->execute($publisher, $this->version->fresh());
    app(ArchiveDesignBundleAction::class)->execute($publisher, $this->version->bundle, 'selesai');

    Storage::disk('b2')->assertExists($file->path);
    expect(Storage::disk('b2')->allFiles())->toHaveCount(1);
});

it('does not store any URL on the file table', function () {
    $file = app(UploadDesignFileAction::class)->execute($this->designer, $this->item, MarketingDesignFileRole::Production, designPdf());

    expect(json_encode($file->fresh()->getAttributes()))->not->toContain('http');
});

describe('download route', function () {
    it('redirects purchasing to a short-lived attachment URL and audits without the URL', function () {
        $file = publishedDesignFile(MarketingDesignFileRole::Production);

        $response = $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.download', $file));

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toStartWith('https://signed.test/'.$file->path)->toContain('attachment')
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');

        $log = MarketingDesignAuditLog::query()->where('event', MarketingDesignAuditEvent::ProductionFileDownloaded)->sole();
        expect($log->actor_id)->toBe($this->purchasing->id)
            ->and($log->marketing_design_file_id)->toBe($file->id)
            ->and(json_encode($log->getAttributes()))->not->toContain('signed.test');
    });

    it('denies source to purchasing and audits an allowed source download', function () {
        $file = publishedDesignFile(MarketingDesignFileRole::Source);

        $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.download', $file))->assertForbidden();
        expect(MarketingDesignAuditLog::query()->where('event', MarketingDesignAuditEvent::SourceFileDownloaded)->count())->toBe(0);

        $this->actingAs($this->designer)->get(route('helpdesk.marketing-design-files.download', $file))->assertRedirect();
        expect(MarketingDesignAuditLog::query()->where('event', MarketingDesignAuditEvent::SourceFileDownloaded)->count())->toBe(1);
    });

    it('rejects guests, users without permission, removed files, and unknown IDs', function () {
        $file = publishedDesignFile(MarketingDesignFileRole::Production);
        $stranger = User::factory()->create(['is_active' => true]);

        $this->get(route('helpdesk.marketing-design-files.download', $file))->assertRedirect();
        $this->actingAs($stranger)->get(route('helpdesk.marketing-design-files.download', $file))->assertForbidden();
        $this->actingAs($this->purchasing)->get('/marketing-design-files/999999/download')->assertNotFound();

        MarketingDesignFile::query()->whereKey($file->id)->update(['deleted_at' => now()]);
        $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.download', $file->id))->assertNotFound();
    });

    it('rejects a tampered path instead of reading storage', function () {
        publishedDesignFile(MarketingDesignFileRole::Production);

        $this->actingAs($this->purchasing)->get('/marketing-design-files/..%2F..%2Fsecret/download')->assertNotFound();
    });

    it('denies purchasing an unpublished file and returns 404 for a missing object', function () {
        $draftFile = MarketingDesignFile::factory()->role(MarketingDesignFileRole::Production)->for($this->item, 'item')->create();
        Storage::disk('b2')->put($draftFile->path, 'binary');
        $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.download', $draftFile))->assertForbidden();

        $missing = publishedDesignFile(MarketingDesignFileRole::Production);
        Storage::disk('b2')->delete($missing->path);
        $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.download', $missing))->assertNotFound();
    });

    it('sanitizes the download filename', function () {
        $file = publishedDesignFile(MarketingDesignFileRole::Production);
        MarketingDesignFile::query()->whereKey($file->id)->update(['original_name' => "../..\\evil\"\r\nX-Header: 1 désain.pdf"]);

        $name = $file->fresh()->safeDownloadName();

        expect($name)->not->toContain('"', "\r", "\n", '/', '\\')->toEndWith('.pdf');

        $location = $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.download', $file))->headers->get('Location');
        expect(urldecode($location))->toContain('attachment; filename="'.$name.'"');
    });
});

describe('preview route', function () {
    it('serves only preview role files inline', function () {
        $preview = publishedDesignFile(MarketingDesignFileRole::Preview);
        $production = publishedDesignFile(MarketingDesignFileRole::Production);

        $location = $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.preview', $preview))->assertRedirect()->headers->get('Location');
        expect(urldecode($location))->toContain('inline');

        $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.preview', $production))->assertForbidden();
        expect(MarketingDesignAuditLog::query()->count())->toBe(0);
    });
});
