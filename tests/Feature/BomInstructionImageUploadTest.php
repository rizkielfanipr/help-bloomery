<?php

use App\Models\RndProject;
use App\Models\RndProjectBom;
use App\Models\RndProjectProduct;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    $this->project = RndProject::query()->create([
        'name' => 'Test R&D Project',
        'start_date' => '2026-07-01',
        'end_date' => '2026-08-31',
        'created_by' => $admin->id,
    ]);
    $this->product = RndProjectProduct::query()->create([
        'rnd_project_id' => $this->project->id,
        'name' => 'Test Product Release',
        'product_code' => 'PRD-TEST',
        'status' => 'development',
        'created_by' => $admin->id,
    ]);
    $this->projectBom = RndProjectBom::query()->create([
        'rnd_project_id' => $this->project->id,
        'esb_bom_id' => 777,
        'bom_code' => 'BOM-777',
        'bom_name' => 'Test Assembly',
        'created_by' => $admin->id,
    ]);
    $this->product->boms()->attach($this->projectBom->id, ['usage_type' => 'main']);
});

function uploadUrl(int $project, int $product, int $bom): string
{
    return route('helpdesk.rnd-products.bom-instruction-images.store', [
        'project' => $project,
        'product' => $product,
        'bom' => $bom,
    ]);
}

it('uploads a BOM instruction image to R2 and returns a stable streaming URL', function () {
    Storage::fake('b2');

    $response = $this->postJson(uploadUrl($this->project->id, $this->product->id, 777), [
        'image' => UploadedFile::fake()->image('foto.jpg', 800, 600),
    ]);

    $response->assertCreated()->assertJsonStructure(['path', 'url']);
    $path = $response->json('path');
    expect($path)->toStartWith("rnd/bom-instructions/{$this->project->id}/{$this->product->id}/777/inline/");
    Storage::disk('b2')->assertExists($path);
    expect($response->json('url'))->toContain(route('helpdesk.rnd-products.bom-instruction-images.show', ['path' => $path]));
});

it('rejects uploading an instruction image for a BOM not attached to the product', function () {
    Storage::fake('b2');

    $this->postJson(uploadUrl($this->project->id, $this->product->id, 999999), [
        'image' => UploadedFile::fake()->image('foto.jpg'),
    ])->assertStatus(422);

    Storage::disk('b2')->assertDirectoryEmpty("rnd/bom-instructions/{$this->project->id}/{$this->product->id}/999999");
});

it('rejects uploading a disallowed file type as an instruction image', function () {
    Storage::fake('b2');

    $this->postJson(uploadUrl($this->project->id, $this->product->id, 777), [
        'image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ])->assertJsonValidationErrors('image');
});

it('rejects uploading an instruction image without the edit rnd projects permission', function () {
    Storage::fake('b2');
    $staff = User::factory()->create(['is_active' => true]);
    $this->actingAs($staff);

    $this->postJson(uploadUrl($this->project->id, $this->product->id, 777), [
        'image' => UploadedFile::fake()->image('foto.jpg'),
    ])->assertForbidden();
});

it('streams a previously uploaded BOM instruction image to an authorized viewer', function () {
    Storage::fake('b2');
    $path = "rnd/bom-instructions/{$this->project->id}/{$this->product->id}/777/inline/".Str::uuid().'.jpg';
    Storage::disk('b2')->put($path, UploadedFile::fake()->image('foto.jpg')->get());

    $this->get(route('helpdesk.rnd-products.bom-instruction-images.show', ['path' => $path]))
        ->assertOk();
});

it('rejects streaming a path outside the instruction-image prefix', function () {
    Storage::fake('b2');
    Storage::disk('b2')->put('rnd/bom-instructions/secrets.env', 'SECRET=1');

    $this->get(route('helpdesk.rnd-products.bom-instruction-images.show', [
        'path' => 'rnd/bom-instructions/secrets.env',
    ]))->assertNotFound();

    $this->get(route('helpdesk.rnd-products.bom-instruction-images.show', [
        'path' => "rnd/bom-instructions/{$this->project->id}/{$this->product->id}/777/inline/../../../../.env",
    ]))->assertNotFound();
});
