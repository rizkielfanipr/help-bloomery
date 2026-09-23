<?php

use App\Actions\Rnd\InternalMemo\GenerateInternalMemoPdfAction;
use App\Enums\RndInternalMemoMenuSyncStatus;
use App\Enums\RndInternalMemoStatus;
use App\Filament\Helpdesk\Resources\RndInternalMemos\Pages\ViewRndInternalMemo;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoMenu;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function finalizedInternalMemoForPdf(): RndInternalMemo
{
    $memo = RndInternalMemo::factory()->finalized()->create();
    $menu = RndInternalMemoMenu::factory()->create([
        'rnd_internal_memo_id' => $memo->id,
        'forecast_quantity' => 10,
        'shelf_life_value' => 3,
        'shelf_life_unit' => 'hari',
        'sync_status' => RndInternalMemoMenuSyncStatus::Synced,
    ]);
    $menu->materials()->create([
        'product_code' => 'RAW-FLOUR', 'product_name' => 'Tepung', 'uom_name' => 'GR',
        'quantity_per_menu' => 250, 'net_quantity' => 2500, 'source_bom_id' => 1, 'source_path' => [],
        'depth' => 0, 'is_wip' => false, 'is_packaging' => false,
    ]);

    return $memo;
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->supervisor = User::factory()->create(['is_active' => true]);
    $this->supervisor->givePermissionTo([
        'view any rnd internal memo', 'view rnd internal memo',
        'generate rnd internal memo pdf', 'download rnd internal memo pdf',
    ]);
    $this->actingAs($this->supervisor);
});

it('generates a PDF document for a Finalized memo with a checksum and file on disk', function () {
    $memo = finalizedInternalMemoForPdf();

    $document = app(GenerateInternalMemoPdfAction::class)->execute($memo, $this->supervisor);

    expect($document->rnd_internal_memo_id)->toBe($memo->id)
        ->and($document->revision)->toBe($memo->revision)
        ->and($document->checksum)->not->toBeNull()
        ->and($document->file_size)->toBeGreaterThan(0)
        ->and($document->generated_by)->toBe($this->supervisor->id);
    Storage::disk('local')->assertExists($document->file_path);
});

it('refuses to generate a PDF for a memo that is not Finalized', function () {
    $memo = RndInternalMemo::factory()->create(['status' => RndInternalMemoStatus::Draft]);

    expect(fn () => app(GenerateInternalMemoPdfAction::class)->execute($memo, $this->supervisor))
        ->toThrow(RuntimeException::class);
});

it('keeps a previous document instead of overwriting it on a second generation', function () {
    $memo = finalizedInternalMemoForPdf();

    $first = app(GenerateInternalMemoPdfAction::class)->execute($memo, $this->supervisor);
    $second = app(GenerateInternalMemoPdfAction::class)->execute($memo, $this->supervisor);

    expect($first->file_path)->not->toBe($second->file_path);
    Storage::disk('local')->assertExists($first->file_path);
    Storage::disk('local')->assertExists($second->file_path);
    expect($memo->documents()->count())->toBe(2);
});

it('dispatches the PDF job from the workspace page and lists the resulting document', function () {
    $memo = finalizedInternalMemoForPdf();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->call('generatePdf');

    expect($memo->documents()->count())->toBe(1);
});

it('hides Generate PDF from a user without the generate permission', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo(['view any rnd internal memo', 'view rnd internal memo']);
    $this->actingAs($viewer);
    $memo = finalizedInternalMemoForPdf();

    Livewire::test(ViewRndInternalMemo::class, ['record' => $memo->id])->assertDontSee('Generate PDF');
});

it('downloads a generated PDF through the authorized route', function () {
    $memo = finalizedInternalMemoForPdf();
    $document = app(GenerateInternalMemoPdfAction::class)->execute($memo, $this->supervisor);

    $response = $this->get(route('helpdesk.rnd-internal-memos.download-pdf', ['memo' => $memo->id, 'document' => $document->id]));

    $response->assertOk();
});

it('downloads a PDF with a safe filename when the memo number contains path separators', function () {
    $memo = finalizedInternalMemoForPdf();
    $memo->update(['memo_number' => 'MI/RND/IX\\2026']);
    $document = app(GenerateInternalMemoPdfAction::class)->execute($memo->fresh(), $this->supervisor);

    $response = $this->get(route('helpdesk.rnd-internal-memos.download-pdf', [
        'memo' => $memo->id,
        'document' => $document->id,
    ]));

    $response->assertOk()
        ->assertDownload('mi-rnd-ix-2026-R1.pdf');
});

it('refuses a download for a user without the download permission', function () {
    $memo = finalizedInternalMemoForPdf();
    $document = app(GenerateInternalMemoPdfAction::class)->execute($memo, $this->supervisor);
    $outsider = User::factory()->create(['is_active' => true]);
    $this->actingAs($outsider);

    $response = $this->get(route('helpdesk.rnd-internal-memos.download-pdf', ['memo' => $memo->id, 'document' => $document->id]));

    $response->assertForbidden();
});

it('404s when the document does not belong to the given memo', function () {
    $memoA = finalizedInternalMemoForPdf();
    $memoB = finalizedInternalMemoForPdf();
    $document = app(GenerateInternalMemoPdfAction::class)->execute($memoA, $this->supervisor);

    $response = $this->get(route('helpdesk.rnd-internal-memos.download-pdf', ['memo' => $memoB->id, 'document' => $document->id]));

    $response->assertNotFound();
});
