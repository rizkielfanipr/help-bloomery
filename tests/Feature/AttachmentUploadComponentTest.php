<?php

use App\Filament\Employee\Modules\Content\Pages\ContentRequestPage;
use App\Filament\Employee\Modules\Design\Pages\DesignRequestPage;
use App\Filament\Employee\Modules\Erp\Pages\ErpRequestPage;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

it('retains upload and removal on each consuming request page', function (string $pageClass, string $buttonText) {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('employee'));

    Livewire::test($pageClass)
        ->assertSee($buttonText)
        ->set('attachments', [UploadedFile::fake()->create('reference.pdf', 10, 'application/pdf')])
        ->assertSee('reference.pdf')
        ->call('removeAttachment', 0)
        ->assertSet('attachments', [])
        ->assertDontSee('reference.pdf');
})->with([
    [ErpRequestPage::class, 'Tambah File / Screenshot'],
    [DesignRequestPage::class, 'Tambah File / Referensi'],
    [ContentRequestPage::class, 'Tambah File / Referensi'],
]);

it('supports nested bindings and caller file restrictions while escaping filenames', function () {
    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['qc.photos.0' => 'Foto tidak valid'])));
    $html = Blade::render('<x-attachment-upload :files="$files" model="qc.photos" remove-action="removeQcPhoto" accept="image/*" />', [
        'files' => [UploadedFile::fake()->create('<script>.pdf', 10)],
    ]);

    expect($html)->toContain('wire:model="qc.photos"', 'removeQcPhoto(0)', 'accept="image/*"', '&lt;script&gt;.pdf', 'Foto tidak valid')
        ->not->toContain('<script>.pdf');
});
