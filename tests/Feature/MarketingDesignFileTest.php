<?php

use App\Actions\BrandMarketing\DesignMaster\ArchiveDesignBundleAction;
use App\Actions\BrandMarketing\DesignMaster\DeleteDesignItemAction;
use App\Actions\BrandMarketing\DesignMaster\ReplaceDesignFileAction;
use App\Actions\BrandMarketing\DesignMaster\SaveDesignItemAction;
use App\Enums\MarketingDesignAuditEvent;
use App\Enums\MarketingDesignBundleStatus;
use App\Models\MarketingDesignAuditLog;
use App\Models\MarketingDesignBundle;
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

    $this->bundle = MarketingDesignBundle::factory()->create();
});

function designPdf(string $name = 'desain final.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 100, 'application/pdf');
}

function addDesignItem(UploadedFile $file, array $data = []): MarketingDesignItem
{
    return app(SaveDesignItemAction::class)->create(test()->designer, test()->bundle, $data + ['type' => 'packaging_design', 'title' => 'Box Kue'], $file);
}

function storedDesignFile(bool $image = false): MarketingDesignFile
{
    $file = $image ? MarketingDesignFile::factory()->image()->create() : MarketingDesignFile::factory()->create();
    Storage::disk('b2')->put($file->path, 'binary');

    return $file;
}

it('creates the item and its private file together, with metadata and checksum', function () {
    $upload = designPdf();

    $item = addDesignItem($upload, ['description' => 'Kemasan utama']);
    $file = $item->file;
    $bundle = $this->bundle;

    expect($item->title)->toBe('Box Kue')
        ->and($item->description)->toBe('Kemasan utama')
        ->and($file->path)->toMatch('#^brand-marketing/design-master/brand-'.$bundle->brand_id.'/bundle-'.$bundle->id.'/[0-9a-f-]{36}\.pdf$#')
        ->and($file->path)->not->toContain('desain')
        ->and($file->disk)->toBe('b2')
        ->and($file->original_name)->toBe('desain final.pdf')
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->extension)->toBe('pdf')
        ->and($file->size_bytes)->toBe($upload->getSize())
        ->and($file->checksum_sha256)->toBe(hash_file('sha256', $upload->getRealPath()))
        ->and($file->uploaded_by)->toBe($this->designer->id);

    Storage::disk('b2')->assertExists($file->path);
    expect(MarketingDesignAuditLog::query()->where('event', MarketingDesignAuditEvent::ItemAdded)->sole()->marketing_design_file_id)->toBe($file->id);
});

it('requires a design file when adding an item', function () {
    expect(fn () => app(SaveDesignItemAction::class)->create($this->designer, $this->bundle, ['type' => 'other', 'title' => 'Tanpa File'], null))
        ->toThrow(ValidationException::class, 'File desain wajib diunggah');

    expect(MarketingDesignItem::count())->toBe(0);
});

it('accepts and rejects formats', function (string $name, string $mime, bool $accepted) {
    $action = fn () => addDesignItem(UploadedFile::fake()->create($name, 10, $mime));

    if ($accepted) {
        expect($action()->file->extension)->toBe(pathinfo($name, PATHINFO_EXTENSION) === 'jpeg' ? 'jpg' : pathinfo($name, PATHINFO_EXTENSION));

        return;
    }

    expect($action)->toThrow(ValidationException::class);
    expect(Storage::disk('b2')->allFiles())->toBeEmpty()
        ->and(MarketingDesignItem::count())->toBe(0)
        ->and(MarketingDesignFile::count())->toBe(0);
})->with([
    'jpg' => ['a.jpg', 'image/jpeg', true],
    'jpeg' => ['a.jpeg', 'image/jpeg', true],
    'png' => ['a.png', 'image/png', true],
    'webp' => ['a.webp', 'image/webp', true],
    'pdf' => ['a.pdf', 'application/pdf', true],
    'zip' => ['a.zip', 'application/zip', true],
    'svg' => ['a.svg', 'image/svg+xml', false],
    'psd' => ['a.psd', 'image/vnd.adobe.photoshop', false],
    'exe' => ['a.exe', 'application/x-msdownload', false],
    'renamed executable' => ['a.pdf', 'application/x-msdownload', false],
]);

it('enforces the size limit shared with the Livewire upload rule', function () {
    $limit = MarketingDesignFileStore::MAX_KILOBYTES;
    expect($limit)->toBe(12288);

    expect(fn () => addDesignItem(UploadedFile::fake()->create('a.pdf', $limit + 1, 'application/pdf')))
        ->toThrow(ValidationException::class, 'melebihi batas');

    expect(addDesignItem(UploadedFile::fake()->create('b.pdf', $limit, 'application/pdf'))->file)->toBeInstanceOf(MarketingDesignFile::class);
});

it('removes the new object and leaves no item when the database write fails', function () {
    MarketingDesignFile::creating(fn () => throw new RuntimeException('database down'));

    expect(fn () => addDesignItem(designPdf()))->toThrow(RuntimeException::class, 'database down');

    expect(Storage::disk('b2')->allFiles())->toBeEmpty()
        ->and(MarketingDesignItem::count())->toBe(0)
        ->and(MarketingDesignFile::count())->toBe(0);
});

it('removes the new object when the bundle was archived during the upload', function () {
    $bundle = $this->bundle;
    $store = Mockery::mock(MarketingDesignFileStore::class)->makePartial();
    $store->shouldReceive('store')->once()->andReturnUsing(function () use ($bundle): array {
        $bundle->update(['status' => MarketingDesignBundleStatus::Archived]);
        Storage::disk('b2')->put('brand-marketing/design-master/orphan.pdf', 'x');

        return ['disk' => 'b2', 'path' => 'brand-marketing/design-master/orphan.pdf', 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 1, 'checksum_sha256' => str_repeat('b', 64)];
    });
    app()->instance(MarketingDesignFileStore::class, $store);

    expect(fn () => addDesignItem(designPdf()))->toThrow(AuthorizationException::class);

    Storage::disk('b2')->assertMissing('brand-marketing/design-master/orphan.pdf');
    expect(MarketingDesignItem::count())->toBe(0);
});

it('reports a storage failure without leaving a record', function () {
    Storage::shouldReceive('disk')->with('b2')->andReturn(new class
    {
        public function putFileAs(): bool
        {
            return false;
        }
    });

    expect(fn () => addDesignItem(designPdf()))->toThrow(ValidationException::class, 'gagal disimpan');

    expect(MarketingDesignItem::count())->toBe(0);
});

it('replaces the file with a new record and path while keeping the old object', function () {
    $item = addDesignItem(designPdf('lama.pdf'));
    $old = $item->file;

    $new = app(ReplaceDesignFileAction::class)->execute($this->designer, $item, designPdf('baru.pdf'));

    expect($new->path)->not->toBe($old->path)
        ->and($old->fresh()->trashed())->toBeTrue()
        ->and($item->fresh()->file->id)->toBe($new->id)
        ->and(MarketingDesignFile::query()->where('marketing_design_item_id', $item->id)->count())->toBe(1);

    Storage::disk('b2')->assertExists($old->path);
    Storage::disk('b2')->assertExists($new->path);
    expect(MarketingDesignAuditLog::query()->where('event', MarketingDesignAuditEvent::FileReplaced)->sole()->metadata['replaced_file_ids'])->toBe([$old->id]);
});

it('keeps the current file when a replacement is rejected', function () {
    $item = addDesignItem(designPdf());
    $current = $item->file;

    expect(fn () => app(ReplaceDesignFileAction::class)->execute($this->designer, $item, UploadedFile::fake()->create('a.svg', 10, 'image/svg+xml')))
        ->toThrow(ValidationException::class);

    expect($current->fresh()->trashed())->toBeFalse()
        ->and($item->fresh()->file->id)->toBe($current->id);
});

it('keeps objects when the bundle is archived', function () {
    $item = addDesignItem(designPdf());
    $archiver = User::factory()->create(['is_active' => true]);
    $archiver->givePermissionTo('view design master bundles', 'archive design master bundles');

    app(ArchiveDesignBundleAction::class)->execute($archiver, $this->bundle, 'selesai');

    Storage::disk('b2')->assertExists($item->file->path);
});

it('permanently deletes an item with its current and replaced files and their objects, keeping the audit', function () {
    $item = addDesignItem(designPdf('lama.pdf'));
    $old = $item->file;
    $new = app(ReplaceDesignFileAction::class)->execute($this->designer, $item, designPdf('baru.pdf'));
    $other = addDesignItem(designPdf('lain.pdf'), ['title' => 'Item Lain']);

    app(DeleteDesignItemAction::class)->execute($this->designer, $item);

    expect(MarketingDesignItem::query()->whereKey($item->id)->exists())->toBeFalse()
        ->and(MarketingDesignFile::withTrashed()->whereKey([$old->id, $new->id])->exists())->toBeFalse()
        ->and($other->fresh()->file)->not->toBeNull();

    Storage::disk('b2')->assertMissing($old->path);
    Storage::disk('b2')->assertMissing($new->path);
    Storage::disk('b2')->assertExists($other->file->path);

    $log = MarketingDesignAuditLog::query()->where('event', MarketingDesignAuditEvent::ItemDeleted)->sole();
    expect($log->actor_id)->toBe($this->designer->id)
        ->and($log->metadata)->toMatchArray(['item_id' => $item->id, 'title' => 'Box Kue', 'deleted_file_count' => 2])
        ->and(MarketingDesignAuditLog::query()->where('event', MarketingDesignAuditEvent::ItemAdded)->count())->toBe(2);
});

it('keeps the records when deleting is refused, and deletes nothing in storage', function () {
    $item = addDesignItem(designPdf());
    $this->bundle->update(['status' => MarketingDesignBundleStatus::Archived]);

    expect(fn () => app(DeleteDesignItemAction::class)->execute($this->designer, $item))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteDesignItemAction::class)->execute($this->purchasing, $item))->toThrow(AuthorizationException::class);

    expect($item->fresh())->not->toBeNull();
    Storage::disk('b2')->assertExists($item->file->path);
});

it('does not store any URL on the file table', function () {
    $file = addDesignItem(designPdf())->file;

    expect(json_encode($file->fresh()->getAttributes()))->not->toContain('http');
});

describe('download route', function () {
    it('redirects purchasing to a short-lived attachment URL and audits without the URL', function () {
        $file = storedDesignFile();

        $response = $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.download', $file));

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toStartWith('https://signed.test/'.$file->path)->toContain('attachment')
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');

        $log = MarketingDesignAuditLog::query()->where('event', MarketingDesignAuditEvent::FileDownloaded)->sole();
        expect($log->actor_id)->toBe($this->purchasing->id)
            ->and($log->marketing_design_file_id)->toBe($file->id)
            ->and(json_encode($log->getAttributes()))->not->toContain('signed.test');
    });

    it('requires the download permission', function () {
        $file = storedDesignFile();
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('view design master bundles');

        $this->actingAs($viewer)->get(route('helpdesk.marketing-design-files.download', $file))->assertForbidden();
        expect(MarketingDesignAuditLog::count())->toBe(0);
    });

    it('rejects guests, users without permission, replaced files, and unknown IDs', function () {
        $file = storedDesignFile();
        $stranger = User::factory()->create(['is_active' => true]);

        $this->get(route('helpdesk.marketing-design-files.download', $file))->assertRedirect();
        $this->actingAs($stranger)->get(route('helpdesk.marketing-design-files.download', $file))->assertForbidden();
        $this->actingAs($this->purchasing)->get('/marketing-design-files/999999/download')->assertNotFound();

        MarketingDesignFile::query()->whereKey($file->id)->update(['deleted_at' => now()]);
        $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.download', $file->id))->assertNotFound();
    });

    it('rejects a tampered path instead of reading storage', function () {
        storedDesignFile();

        $this->actingAs($this->purchasing)->get('/marketing-design-files/..%2F..%2Fsecret/download')->assertNotFound();
    });

    it('denies purchasing the file of an archived bundle and returns 404 for a missing object', function () {
        $archivedFile = storedDesignFile();
        $archivedFile->item->bundle->update(['status' => MarketingDesignBundleStatus::Archived]);
        $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.download', $archivedFile))->assertForbidden();

        $missing = storedDesignFile();
        Storage::disk('b2')->delete($missing->path);
        $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.download', $missing))->assertNotFound();
    });

    it('sanitizes the download filename', function () {
        $file = storedDesignFile();
        MarketingDesignFile::query()->whereKey($file->id)->update(['original_name' => "../..\\evil\"\r\nX-Header: 1 désain.pdf"]);

        $name = $file->fresh()->safeDownloadName();

        expect($name)->not->toContain('"', "\r", "\n", '/', '\\')->toEndWith('.pdf');

        $location = $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.download', $file))->headers->get('Location');
        expect(urldecode($location))->toContain('attachment; filename="'.$name.'"');
    });
});

describe('preview route', function () {
    it('serves images and PDFs inline without auditing, but never ZIP files', function () {
        $image = storedDesignFile(image: true);
        $zip = MarketingDesignFile::factory()->create(['mime_type' => 'application/zip', 'extension' => 'zip', 'original_name' => 'a.zip']);

        $location = $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.preview', $image))->assertRedirect()->headers->get('Location');
        expect(urldecode($location))->toContain('inline');

        $this->actingAs($this->purchasing)->get(route('helpdesk.marketing-design-files.preview', $zip))->assertForbidden();
        expect(MarketingDesignAuditLog::query()->count())->toBe(0);
    });
});
