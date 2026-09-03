<?php

use App\Enums\SalesReportStatus;
use App\Filament\Casual\Pages\SalesReportShiftPage;
use App\Filament\Helpdesk\Resources\ComplimentTypes\ComplimentTypeResource;
use App\Filament\Helpdesk\Resources\ComplimentTypes\Pages\CreateComplimentType;
use App\Filament\Helpdesk\Resources\ComplimentTypes\Pages\EditComplimentType;
use App\Filament\Helpdesk\Resources\ComplimentTypes\Pages\ListComplimentTypes;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ViewSalesReport;
use App\Models\Branch;
use App\Models\ComplimentType;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\User;
use Database\Seeders\ComplimentTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(ComplimentTypeSeeder::class);

    $this->branch = Branch::factory()->create(['sales_shift_count' => 2]);
    $this->staff = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->staff->assignRole('CASUAL_STAFF');
    $this->employee = Employee::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
});

it('seeds the default compliment types', function () {
    expect(ComplimentType::query()->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Compliment Owner', 'Compliment Training']);
});

it('submits multiple compliments and multiple nota attachments for a shift', function () {
    Storage::fake('b2');
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->staff);

    $owner = ComplimentType::where('name', 'Compliment Owner')->sole();
    $training = ComplimentType::where('name', 'Compliment Training')->sole();

    Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString(), 'shiftNumber' => 1])
        ->set('esbFetched', true)
        ->set('rows', [[
            'label' => 'TAKEAWAY', 'name' => 'CASH', 'sales_store' => '100000', 'notes' => '',
        ]])
        ->set('employeeIds', [$this->employee->id])
        ->set('compliments', [
            [
                'compliment_type_id' => $owner->id,
                'attachments' => [],
                'notes' => 'Compliment untuk tamu owner.',
            ],
            [
                'compliment_type_id' => $training->id,
                'attachments' => [],
                'notes' => 'Compliment kebutuhan training.',
            ],
        ])
        ->set('compliments.0.attachments', [
            UploadedFile::fake()->image('owner-1.jpg'),
            UploadedFile::fake()->create('owner-2.pdf', 100, 'application/pdf'),
        ])
        ->set('compliments.1.attachments', [UploadedFile::fake()->image('training.jpg')])
        ->call('requestConfirm')
        ->assertHasNoErrors()
        ->call('save')
        ->assertHasNoErrors();

    $report = SalesReport::whereBelongsTo($this->branch)->whereDate('report_date', today())->sole();
    $compliments = $report->compliments()->orderBy('id')->get();

    expect($compliments)->toHaveCount(2)
        ->and($compliments[0]->compliment_type_name)->toBe('Compliment Owner')
        ->and($compliments[0]->attachment_paths)->toHaveCount(2)
        ->and($compliments[0]->notes)->toBe('Compliment untuk tamu owner.')
        ->and($compliments[1]->compliment_type_name)->toBe('Compliment Training')
        ->and($compliments[1]->attachment_paths)->toHaveCount(1)
        ->and($compliments[1]->shift_number)->toBe(1);

    foreach ($compliments->flatMap->attachment_paths as $path) {
        Storage::disk('b2')->assertExists($path);
    }
});

it('supports adding and removing compliments and attachments interactively', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->staff);

    $owner = ComplimentType::where('name', 'Compliment Owner')->sole();

    Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString(), 'shiftNumber' => 1])
        ->call('addCompliment')
        ->assertSet('compliments.0.compliment_type_id', null)
        ->set('compliments.0.compliment_type_id', $owner->id)
        ->set('compliments.0.attachments', [
            UploadedFile::fake()->image('nota1.jpg'),
            UploadedFile::fake()->image('nota2.jpg'),
        ])
        ->call('removeComplimentAttachment', 0, 0)
        ->assertCount('compliments.0.attachments', 1)
        ->call('removeCompliment', 0)
        ->assertCount('compliments', 0);
});

it('rejects inactive types and compliments without nota or notes', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->staff);
    $inactiveType = ComplimentType::factory()->create(['is_active' => false]);

    Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString(), 'shiftNumber' => 1])
        ->set('esbFetched', true)
        ->set('rows', [['label' => 'TAKEAWAY', 'name' => 'CASH', 'sales_store' => '100000', 'notes' => '']])
        ->set('employeeIds', [$this->employee->id])
        ->set('compliments', [[
            'compliment_type_id' => $inactiveType->id,
            'attachments' => [],
            'notes' => '',
        ]])
        ->call('requestConfirm')
        ->assertHasErrors([
            'compliments.0.compliment_type_id',
            'compliments.0.attachments',
            'compliments.0.notes',
        ]);
});

it('shows submitted compliments on the back office sales report detail', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $reviewer = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $reviewer->givePermissionTo('view sales reports');
    $this->actingAs($reviewer);

    $type = ComplimentType::where('name', 'Compliment Owner')->sole();
    $report = SalesReport::factory()->create([
        'branch_id' => $this->branch->id,
        'status' => SalesReportStatus::PendingSupervisor,
    ]);
    $report->compliments()->create([
        'shift_number' => 1,
        'compliment_type_id' => $type->id,
        'compliment_type_name' => $type->name,
        'attachment_paths' => [],
        'notes' => 'Tamu owner mendapatkan compliment.',
        'submitted_by' => $this->staff->id,
    ]);

    Livewire::test(ViewSalesReport::class, ['record' => $report])
        ->assertSee('Compliment per Shift')
        ->assertSee('Compliment Owner')
        ->assertSee('Tamu owner mendapatkan compliment.')
        ->assertSee('Shift 1');
});

it('deletes stored compliment attachments with its sales report', function () {
    Storage::fake('b2');
    Storage::disk('b2')->put('sales-report-compliments/nota.jpg', 'image');

    $type = ComplimentType::where('name', 'Compliment Owner')->sole();
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id]);
    $report->compliments()->create([
        'shift_number' => 1,
        'compliment_type_id' => $type->id,
        'compliment_type_name' => $type->name,
        'attachment_paths' => ['sales-report-compliments/nota.jpg'],
        'notes' => 'Catatan',
    ]);

    $report->delete();

    Storage::disk('b2')->assertMissing('sales-report-compliments/nota.jpg');
});

it('deletes stored attachments when an individual compliment model is deleted', function () {
    Storage::fake('b2');
    Storage::disk('b2')->put('sales-report-compliments/single-nota.jpg', 'image');

    $type = ComplimentType::where('name', 'Compliment Owner')->sole();
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id]);
    $compliment = $report->compliments()->create([
        'shift_number' => 1,
        'compliment_type_id' => $type->id,
        'compliment_type_name' => $type->name,
        'attachment_paths' => ['sales-report-compliments/single-nota.jpg'],
        'notes' => 'Catatan single delete',
    ]);

    $compliment->delete();

    Storage::disk('b2')->assertMissing('sales-report-compliments/single-nota.jpg');
});

it('enforces permissions for compliment types in back office', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $unauthorizedUser = User::factory()->create(['is_active' => true]);
    $this->actingAs($unauthorizedUser);

    Livewire::test(ListComplimentTypes::class)
        ->assertForbidden();

    $financeUser = User::factory()->create(['is_active' => true]);
    $financeUser->assignRole('FINANCE_STAFF');
    $this->actingAs($financeUser);

    Livewire::test(ListComplimentTypes::class)
        ->assertSuccessful()
        ->assertSee('Compliment Owner')
        ->assertSee('Compliment Training');
});

it('can create, edit, and delete compliment types in back office', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $financeUser = User::factory()->create(['is_active' => true]);
    $financeUser->assignRole('FINANCE_STAFF');
    $this->actingAs($financeUser);

    Livewire::test(CreateComplimentType::class)
        ->fillForm([
            'name' => 'Compliment Marketing',
            'sort_order' => 3,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = ComplimentType::where('name', 'Compliment Marketing')->sole();
    expect($created->sort_order)->toBe(3)
        ->and($created->is_active)->toBeTrue();

    Livewire::test(EditComplimentType::class, [
        'record' => $created->getRouteKey(),
    ])
        ->fillForm([
            'name' => 'Compliment Promosi',
            'sort_order' => 4,
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($created->fresh()->name)->toBe('Compliment Promosi')
        ->and($created->fresh()->is_active)->toBeFalse();

    Livewire::test(EditComplimentType::class, [
        'record' => $created->getRouteKey(),
    ])
        ->callAction('delete')
        ->assertHasNoErrors();

    expect(ComplimentType::where('id', $created->id)->exists())->toBeFalse();
});

it('prevents deleting compliment types that are associated with sales report compliments', function () {
    $ownerType = ComplimentType::where('name', 'Compliment Owner')->sole();
    $report = SalesReport::factory()->create(['branch_id' => $this->branch->id]);
    $report->compliments()->create([
        'shift_number' => 1,
        'compliment_type_id' => $ownerType->id,
        'compliment_type_name' => $ownerType->name,
        'attachment_paths' => [],
        'notes' => 'Terkait sales report',
    ]);

    $financeUser = User::factory()->create(['is_active' => true]);
    $financeUser->assignRole('FINANCE_STAFF');
    $this->actingAs($financeUser);

    expect(ComplimentTypeResource::canDelete($ownerType))->toBeFalse();
});
