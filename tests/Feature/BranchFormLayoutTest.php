<?php

use App\Filament\Helpdesk\Resources\Branches\Pages\CreateBranch;
use App\Filament\Helpdesk\Resources\Branches\Pages\EditBranch;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
});

it('lays the create page out as an identity header and a single form card with grouped settings', function () {
    $html = Livewire::test(CreateBranch::class)
        ->assertSee('Master · Branch')
        ->assertSee('Tambah Branch')
        ->assertSee('Daftar Branch')
        ->assertSeeInOrder(['Informasi Branch', 'Kode ESB', 'Shift Basket Size', 'Lokasi Absen'])
        ->assertSee('Simpan Branch')
        ->assertSee('Simpan & Tambah Lagi')
        ->assertSee('Batal')
        ->assertDontSee('Hitung Ulang')
        ->html();

    expect($html)->toContain('fi-workspace-form')
        ->and(substr_count($html, 'fi-section-not-contained'))->toBe(4)
        ->and($html)->not->toContain('fi-fo-repeater-item')
        ->and($html)->toContain('fi-fo-table-repeater');
});

it('shows the branch identity and a Basket Size hint on the edit page', function () {
    $branch = Branch::factory()->create(['name' => 'Kitchen Jateng', 'is_active' => true]);

    Livewire::test(EditBranch::class, ['record' => $branch->id])
        ->assertSee('Kitchen Jateng')
        ->assertSee('Aktif')
        ->assertSee('Simpan Perubahan')
        ->assertSee('Setelah jam shift diubah, Basket Size yang sudah final perlu dihitung ulang.')
        ->assertSee('Hitung Ulang');

    $branch->update(['is_active' => false]);

    Livewire::test(EditBranch::class, ['record' => $branch->id])->assertSee('Nonaktif');
});

it('keeps saving every group of the branch form with the reorganised layout', function () {
    Livewire::test(CreateBranch::class)
        ->fillForm([
            'name' => 'Kitchen Baru',
            'address' => 'Jl. Contoh No. 1',
            'is_active' => true,
            'location_required' => true,
            'esbCodes' => [
                ['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO16', 'label' => 'DINE IN', 'is_active' => true],
            ],
            'salesShifts' => [
                ['shift_number' => 1, 'name' => 'Shift 1', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $branch = Branch::where('name', 'Kitchen Baru')->firstOrFail();

    expect($branch->location_required)->toBeTrue()
        ->and($branch->address)->toBe('Jl. Contoh No. 1')
        ->and($branch->esbCodes()->count())->toBe(1)
        ->and($branch->salesShifts()->orderBy('shift_number')->pluck('name')->all())->toBe(['Shift 1']);

    Livewire::test(EditBranch::class, ['record' => $branch->id])
        ->fillForm(['name' => 'Kitchen Baru Jateng', 'location_required' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($branch->fresh())
        ->name->toBe('Kitchen Baru Jateng')
        ->location_required->toBeFalse();
});

it('starts a new branch with one empty row for the ESB codes and one for the shifts', function () {
    $page = Livewire::test(CreateBranch::class);

    expect($page->get('data.esbCodes'))->toHaveCount(1)
        ->and($page->get('data.salesShifts'))->toHaveCount(1)
        ->and(collect($page->get('data.esbCodes'))->first())->toMatchArray(['esb_branch_code' => null, 'esb_comcode' => null])
        ->and(collect($page->get('data.salesShifts'))->first())->toMatchArray(['shift_number' => null, 'start_time' => null, 'end_time' => null]);

    $page->fillForm(['name' => 'Tanpa Isian'])
        ->call('create')
        ->assertHasFormErrors();

    expect(Branch::where('name', 'Tanpa Isian')->exists())->toBeFalse();
});

it('lets a new branch skip the ESB codes and shifts by removing their rows', function () {
    Livewire::test(CreateBranch::class)
        ->fillForm(['name' => 'Tanpa ESB', 'esbCodes' => [], 'salesShifts' => []])
        ->call('create')
        ->assertHasNoFormErrors();

    $branch = Branch::where('name', 'Tanpa ESB')->firstOrFail();

    expect($branch->esbCodes()->count())->toBe(0)
        ->and($branch->salesShifts()->count())->toBe(0);
});

it('does not force an empty row on the edit page of a branch without ESB codes or shifts', function () {
    $branch = Branch::factory()->create(['name' => 'Cabang Lama']);

    $page = Livewire::test(EditBranch::class, ['record' => $branch->id]);

    expect($page->get('data.esbCodes'))->toBeEmpty()
        ->and($page->get('data.salesShifts'))->toBeEmpty();

    $page->fillForm(['name' => 'Cabang Lama Baru'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($branch->fresh()->name)->toBe('Cabang Lama Baru');
});

it('offers the current location as a floating icon button on the map instead of a GPS text button', function () {
    $html = Livewire::test(CreateBranch::class)
        ->assertSeeHtml('aria-label="Gunakan lokasi saya"')
        ->assertSeeHtml('title="Lokasi saya"')
        ->assertDontSee('GPS Saya')
        ->assertDontSee('Mencari...')
        ->html();

    $mapStart = strpos($html, 'x-ref="mapEl"');
    $buttonAt = strpos($html, 'aria-label="Gunakan lokasi saya"');
    $mapWrapper = strrpos(substr($html, 0, $mapStart), 'wire:ignore class="relative"');

    expect($mapWrapper)->not->toBeFalse()
        ->and($buttonAt)->toBeGreaterThan($mapStart)
        ->and(substr($html, $buttonAt - 400, 400))->toContain('@click="useGps()"');
    expect(substr($html, $buttonAt, 700))->toContain('absolute bottom-8 right-3');
});
