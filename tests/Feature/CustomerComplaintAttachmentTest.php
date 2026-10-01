<?php

use App\Enums\CustomerComplaintCategory;
use App\Enums\CustomerComplaintSource;
use App\Filament\Casual\Pages\CustomerComplaintPage;
use App\Models\Branch;
use App\Models\CustomerComplaint;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('b2');
    $this->branch = Branch::factory()->create();
    $this->submitter = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
    $this->submitter->givePermissionTo('create customer complaints');
});

function attachedComplaint(Branch $branch, User $submitter): CustomerComplaint
{
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    Livewire::actingAs($submitter);

    Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $branch->id)
        ->set([
            'occurredAt' => now()->subHour()->format('Y-m-d\TH:i'),
            'source' => CustomerComplaintSource::InStore->value,
            'category' => CustomerComplaintCategory::Service->value,
            'description' => 'Ada rambut di makanan.',
        ])
        ->set('attachments', [UploadedFile::fake()->image('bukti.jpg')])
        ->call('submit')
        ->assertHasNoErrors();

    return CustomerComplaint::query()->sole();
}

it('lets the submitter and a branch-scoped reviewer open the attachment through the authorized route', function () {
    $complaint = attachedComplaint($this->branch, $this->submitter);
    $path = $complaint->attachment_paths[0];

    $this->actingAs($this->submitter)
        ->get(route('helpdesk.customer-complaints.attachments.show', ['path' => $path]))
        ->assertOk();

    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $this->branch->id]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints']);
    $this->actingAs($reviewer)
        ->get(route('helpdesk.customer-complaints.attachments.show', ['path' => $path]))
        ->assertOk();
});

it('refuses an outsider or a reviewer without branch access', function () {
    $complaint = attachedComplaint($this->branch, $this->submitter);
    $path = $complaint->attachment_paths[0];

    $outsider = User::factory()->create(['is_active' => true]);
    $this->actingAs($outsider)
        ->get(route('helpdesk.customer-complaints.attachments.show', ['path' => $path]))
        ->assertForbidden();

    $reviewerElsewhere = User::factory()->create(['is_active' => true, 'branch_id' => Branch::factory()->create()->id]);
    $reviewerElsewhere->givePermissionTo(['view any customer complaints', 'view customer complaints']);
    $this->actingAs($reviewerElsewhere)
        ->get(route('helpdesk.customer-complaints.attachments.show', ['path' => $path]))
        ->assertForbidden();
});

it('404s for a path that is not registered on any complaint, instead of serving any file on the disk', function () {
    attachedComplaint($this->branch, $this->submitter);
    Storage::disk('b2')->put('customer-complaints/'.$this->branch->id.'/not-registered.jpg', 'fake-bytes');

    $this->actingAs($this->submitter)
        ->get(route('helpdesk.customer-complaints.attachments.show', ['path' => 'customer-complaints/'.$this->branch->id.'/not-registered.jpg']))
        ->assertNotFound();
});

it('deletes already-uploaded files when the Action rejects the submission, instead of leaving them orphaned', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $otherBranch = Branch::factory()->create();

    $this->actingAs($this->submitter);
    $component = Livewire::test(CustomerComplaintPage::class)
        ->set('branchId', $otherBranch->id) // not accessible to $this->submitter
        ->set([
            'occurredAt' => now()->subHour()->format('Y-m-d\TH:i'),
            'source' => CustomerComplaintSource::InStore->value,
            'category' => CustomerComplaintCategory::Service->value,
            'description' => 'Deskripsi komplain.',
        ])
        ->set('attachments', [UploadedFile::fake()->image('bukti.jpg')]);

    $component->call('submit')->assertHasErrors(['branch_id']);

    // The branch rejection happens inside CreateCustomerComplaintAction, after the file is
    // already stored by the Page — assert nothing was left behind on the disk.
    Storage::disk('b2')->assertDirectoryEmpty('customer-complaints/'.$otherBranch->id);
    expect(CustomerComplaint::query()->count())->toBe(0);
});

it('does not delete attachments from disk when a complaint is soft-deleted', function () {
    $complaint = attachedComplaint($this->branch, $this->submitter);
    $path = $complaint->attachment_paths[0];
    Storage::disk('b2')->assertExists($path);

    $complaint->delete();

    Storage::disk('b2')->assertExists($path);
    expect(CustomerComplaint::withTrashed()->find($complaint->id)->trashed())->toBeTrue();
});
