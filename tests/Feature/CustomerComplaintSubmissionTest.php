<?php

use App\Enums\CustomerComplaintCategory;
use App\Enums\CustomerComplaintSource;
use App\Filament\Casual\Pages\CustomerComplaintPage;
use App\Filament\Casual\Pages\LauncherPage;
use App\Models\Branch;
use App\Models\CustomerComplaint;
use App\Models\User;
use App\Notifications\CustomerComplaintSubmittedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->user->givePermissionTo('create customer complaints');
    Filament::setCurrentPanel(Filament::getPanel('casual'));
});

function validComplaintFormState(): array
{
    return [
        'occurredAt' => now()->subDay()->format('Y-m-d'),
        'source' => CustomerComplaintSource::InStore->value,
        'category' => CustomerComplaintCategory::Service->value,
        'orderReference' => 'TRX-000123',
        'customerName' => 'Budi',
        'customerContact' => '0812xxxx',
        'description' => 'Pelayanan lambat saat jam sibuk.',
    ];
}

it('shows the Form Komplain tile only to users with the create permission', function () {
    $this->actingAs($this->user);
    $withPermission = Livewire::test(LauncherPage::class);
    $tileLabels = collect($withPermission->instance()->tiles())->pluck('label');
    expect($tileLabels)->toContain('Form Komplain');

    $outsider = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
    $outsider->givePermissionTo('access employee app attendance');
    $this->actingAs($outsider);
    $withoutPermission = Livewire::test(LauncherPage::class);
    expect(collect($withoutPermission->instance()->tiles())->pluck('label'))->not->toContain('Form Komplain');
});

it('defaults the branch field to the user\'s primary branch', function () {
    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintPage::class)
        ->assertSet('branchId', $this->branch->id);
});

it('submits a complaint with a generated number and records the submitter', function () {
    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $this->branch->id)
        ->set(validComplaintFormState())
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $complaint = CustomerComplaint::query()->sole();
    expect($complaint->complaint_number)->toStartWith('CMP-'.now()->format('Ymd').'-')
        ->and($complaint->submitted_by)->toBe($this->user->id)
        ->and($complaint->branch_id)->toBe($this->branch->id);
});

it('shows a bottom nav with Form and Riwayat tabs, and points "Lihat Detail" at the Riwayat page after submit', function () {
    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintPage::class)
        ->assertSee('Riwayat')
        ->assertSeeHtml(route('filament.casual.pages.customer-complaint-history-page'))
        ->set('branchId', $this->branch->id)
        ->set(validComplaintFormState())
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSeeHtml(route('filament.casual.pages.customer-complaint-history-page'));
});

it('rejects a submission for a branch the user cannot access, even if the component state is tampered', function () {
    $otherBranch = Branch::factory()->create();
    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $otherBranch->id)
        ->set(validComplaintFormState())
        ->call('submit')
        ->assertHasErrors();

    expect(CustomerComplaint::query()->count())->toBe(0);
});

it('validates required fields and max lengths', function () {
    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $this->branch->id)
        ->set('occurredAt', '')
        ->set('source', '')
        ->set('category', '')
        ->set('description', '')
        ->call('submit')
        ->assertHasErrors(['occurredAt', 'source', 'category', 'description']);

    Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $this->branch->id)
        ->set(validComplaintFormState())
        ->set('description', str_repeat('a', 2001))
        ->call('submit')
        ->assertHasErrors(['description']);
});

it('accepts Tanggal Kejadian as a plain date with no time component, and rejects a future date', function () {
    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $this->branch->id)
        ->set(validComplaintFormState())
        ->set('occurredAt', now()->format('Y-m-d'))
        ->call('submit')
        ->assertHasNoErrors();

    $complaint = CustomerComplaint::query()->sole();
    expect($complaint->occurred_at->format('H:i:s'))->toBe('00:00:00');

    Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $this->branch->id)
        ->set(validComplaintFormState())
        ->set('occurredAt', now()->addDay()->format('Y-m-d'))
        ->call('submit')
        ->assertHasErrors(['occurredAt']);
});

it('does not create two complaints from a rapid duplicate submit', function () {
    $this->actingAs($this->user);

    $component = Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $this->branch->id)
        ->set(validComplaintFormState());

    // Mirrors the guard in CustomerComplaintPage::submit(): a second call while isSubmitting is
    // still true (e.g. a duplicate click before the button re-renders disabled) must no-op.
    $component->set('isSubmitting', true)->call('submit');
    expect(CustomerComplaint::query()->count())->toBe(0);

    $component->set('isSubmitting', false)->call('submit')->assertHasNoErrors();
    expect(CustomerComplaint::query()->count())->toBe(1);
});

it('uploads valid attachments to the private disk and stores their paths', function () {
    Storage::fake('b2');
    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $this->branch->id)
        ->set(validComplaintFormState())
        ->set('attachments', [UploadedFile::fake()->image('bukti.jpg')])
        ->call('submit')
        ->assertHasNoErrors();

    $complaint = CustomerComplaint::query()->sole();
    expect($complaint->attachment_paths)->toHaveCount(1);
    Storage::disk('b2')->assertExists($complaint->attachment_paths[0]);
});

it('rejects an attachment with a disallowed file type', function () {
    Storage::fake('b2');
    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $this->branch->id)
        ->set(validComplaintFormState())
        ->set('attachments', [UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])
        ->call('submit')
        ->assertHasErrors(['attachments.0']);

    expect(CustomerComplaint::query()->count())->toBe(0);
});

it('rejects an attachment larger than 5 MB', function () {
    Storage::fake('b2');
    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $this->branch->id)
        ->set(validComplaintFormState())
        ->set('attachments', [UploadedFile::fake()->create('bukti.jpg', 5121)])
        ->call('submit')
        ->assertHasErrors(['attachments.0']);
});

it('notifies Operational reviewers who can access the complaint\'s branch, after the transaction commits', function () {
    Notification::fake();

    $reviewerInBranch = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
    $reviewerInBranch->givePermissionTo('view customer complaints');

    $reviewerElsewhere = User::factory()->create(['is_active' => true, 'branch_id' => Branch::factory()->create()->id]);
    $reviewerElsewhere->givePermissionTo('view customer complaints');

    $storeStaffWithoutReviewPermission = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);

    $this->actingAs($this->user);
    Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $this->branch->id)
        ->set(validComplaintFormState())
        ->call('submit')
        ->assertHasNoErrors();

    $complaint = CustomerComplaint::query()->sole();

    Notification::assertSentTo($reviewerInBranch, CustomerComplaintSubmittedNotification::class);
    Notification::assertNotSentTo($reviewerElsewhere, CustomerComplaintSubmittedNotification::class);
    Notification::assertNotSentTo($storeStaffWithoutReviewPermission, CustomerComplaintSubmittedNotification::class);
    expect($complaint->exists)->toBeTrue();
});

it('does not send a notification when the submission fails validation', function () {
    Notification::fake();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
    $reviewer->givePermissionTo('view customer complaints');

    $this->actingAs($this->user);
    Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $this->branch->id)
        ->set('description', '')
        ->call('submit')
        ->assertHasErrors(['description']);

    Notification::assertNothingSent();
});
