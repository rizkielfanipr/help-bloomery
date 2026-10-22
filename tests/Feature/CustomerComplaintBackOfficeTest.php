<?php

use App\Enums\CustomerComplaintCategory;
use App\Enums\CustomerComplaintSource;
use App\Filament\Helpdesk\Resources\CustomerComplaints\CustomerComplaintResource;
use App\Filament\Helpdesk\Resources\CustomerComplaints\Pages\ListCustomerComplaints;
use App\Filament\Helpdesk\Resources\CustomerComplaints\Pages\ViewCustomerComplaint;
use App\Models\Branch;
use App\Models\CustomerComplaint;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
});

it('shows the Customer Complaints menu under Operational in the custom back office sidebar', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    $this->get(route('filament.helpdesk.resources.customer-complaints.index'))
        ->assertOk()
        ->assertSee('Operational')
        ->assertSee('Customer Complaints')
        ->assertSee(route('filament.helpdesk.resources.customer-complaints.index'), false);
});

it('renders Customer Complaint filters inline in the table header', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    Livewire::test(ListCustomerComplaints::class)
        ->assertSuccessful()
        ->assertTableActionDoesNotExist('delete')
        ->assertSee('Cari komplain...')
        ->assertSee('Pilih rentang tanggal')
        ->assertSee('- Semua Branch -')
        ->assertSee('- Semua Category -')
        ->assertSee('- Semua Source -');
});

it('scopes the index to the reviewer\'s accessible branches, plus their own submitted complaints', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $ownBranch->id]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints']);

    $visible = CustomerComplaint::factory()->create(['branch_id' => $ownBranch->id, 'customer_name' => 'Visible Co']);
    $ownSubmission = CustomerComplaint::factory()->create(['branch_id' => $otherBranch->id, 'submitted_by' => $reviewer->id, 'customer_name' => 'Own Submission Co']);
    $hidden = CustomerComplaint::factory()->create(['branch_id' => $otherBranch->id, 'customer_name' => 'Hidden Co']);

    $this->actingAs($reviewer);

    Livewire::test(ListCustomerComplaints::class)
        ->assertCanSeeTableRecords([$visible, $ownSubmission])
        ->assertCanNotSeeTableRecords([$hidden]);
});

it('lets a user with access_all_branches see complaints from every branch', function () {
    $manager = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $manager->givePermissionTo(['view any customer complaints', 'view customer complaints']);

    $complaintA = CustomerComplaint::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $complaintB = CustomerComplaint::factory()->create(['branch_id' => Branch::factory()->create()->id]);

    $this->actingAs($manager);

    Livewire::test(ListCustomerComplaints::class)
        ->assertCanSeeTableRecords([$complaintA, $complaintB]);
});

it('searches by complaint number, order reference, customer name, and description', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id, 'access_all_branches' => true]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints']);

    $byNumber = CustomerComplaint::factory()->create(['branch_id' => $branch->id, 'complaint_number' => 'CMP-20261001-0099']);
    $byOrderRef = CustomerComplaint::factory()->create(['branch_id' => $branch->id, 'order_reference' => 'UNIQUE-ORDER-REF']);
    $byCustomer = CustomerComplaint::factory()->create(['branch_id' => $branch->id, 'customer_name' => 'Zonk Pelanggan']);
    $byDescription = CustomerComplaint::factory()->create(['branch_id' => $branch->id, 'description' => 'Keterangan unik soal rasa kopi pahit']);
    $unrelated = CustomerComplaint::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($reviewer);

    Livewire::test(ListCustomerComplaints::class)
        ->filterTable('search', ['value' => 'CMP-20261001-0099'])
        ->assertCanSeeTableRecords([$byNumber])
        ->assertCanNotSeeTableRecords([$unrelated]);

    Livewire::test(ListCustomerComplaints::class)
        ->filterTable('search', ['value' => 'UNIQUE-ORDER-REF'])
        ->assertCanSeeTableRecords([$byOrderRef]);

    Livewire::test(ListCustomerComplaints::class)
        ->filterTable('search', ['value' => 'Zonk Pelanggan'])
        ->assertCanSeeTableRecords([$byCustomer]);

    Livewire::test(ListCustomerComplaints::class)
        ->filterTable('search', ['value' => 'rasa kopi pahit'])
        ->assertCanSeeTableRecords([$byDescription]);
});

it('filters the index by branch, category, and source', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints']);

    $matching = CustomerComplaint::factory()->create([
        'branch_id' => $branchA->id,
        'category' => CustomerComplaintCategory::ProductQuality,
        'source' => CustomerComplaintSource::InStore,
    ]);
    $otherBranch = CustomerComplaint::factory()->create(['branch_id' => $branchB->id]);
    $otherCategory = CustomerComplaint::factory()->create([
        'branch_id' => $branchA->id,
        'category' => CustomerComplaintCategory::Service,
        'source' => CustomerComplaintSource::InStore,
    ]);

    $this->actingAs($reviewer);

    Livewire::test(ListCustomerComplaints::class)
        ->filterTable('branch_id', $branchA->id)
        ->filterTable('category', CustomerComplaintCategory::ProductQuality->value)
        ->filterTable('source', CustomerComplaintSource::InStore->value)
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$otherBranch, $otherCategory]);
});

it('filters realistic dummy complaints through every inline header filter', function () {
    $branchA = Branch::factory()->create(['name' => 'UI Test Pabelan']);
    $branchB = Branch::factory()->create(['name' => 'UI Test Jakarta']);
    $reviewer = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints']);

    $matching = CustomerComplaint::factory()->create([
        'branch_id' => $branchA->id,
        'complaint_number' => 'UI-CMP-1001',
        'customer_name' => 'Customer Alpha',
        'occurred_at' => '2026-10-01 09:00:00',
        'category' => CustomerComplaintCategory::ProductQuality,
        'source' => CustomerComplaintSource::InStore,
    ]);
    $other = CustomerComplaint::factory()->create([
        'branch_id' => $branchB->id,
        'complaint_number' => 'UI-CMP-2002',
        'customer_name' => 'Customer Beta',
        'occurred_at' => '2026-11-15 10:00:00',
        'category' => CustomerComplaintCategory::Service,
        'source' => CustomerComplaintSource::WhatsApp,
    ]);

    $this->actingAs($reviewer);

    Livewire::test(ListCustomerComplaints::class)
        ->assertSee('UI-CMP-1001')
        ->set('tableFilters.search.value', 'Customer Alpha')
        ->set('tableFilters.branch_id.value', $branchA->id)
        ->set('tableFilters.category.value', CustomerComplaintCategory::ProductQuality->value)
        ->set('tableFilters.source.value', CustomerComplaintSource::InStore->value)
        ->set('tableFilters.occurred_at.from', '2026-10-01')
        ->set('tableFilters.occurred_at.until', '2026-10-10')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});

it('shows complaint details as a read-only recap without follow-up actions', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints', 'update customer complaints']);
    $complaint = CustomerComplaint::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($reviewer);
    Livewire::test(ViewCustomerComplaint::class, ['record' => $complaint->id])
        ->assertSuccessful()
        ->assertActionDoesNotExist('follow_up')
        ->assertDontSee('Tindak Lanjut')
        ->assertDontSee('Rekapan Penanganan')
        ->assertDontSee('Person in Charge')
        ->assertDontSee('Internal Notes')
        ->assertDontSee('Resolution');

    expect($complaint->refresh()->getAttributes())->not->toHaveKey('status');
});

it('renders every complaint attachment as an authorized link', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints']);
    $paths = [
        "customer-complaints/{$branch->id}/foto-awal.jpg",
        "customer-complaints/{$branch->id}/foto-kedua.jpg",
    ];
    $complaint = CustomerComplaint::factory()->create([
        'branch_id' => $branch->id,
        'attachment_paths' => $paths,
    ]);

    $this->actingAs($reviewer);

    Livewire::test(ViewCustomerComplaint::class, ['record' => $complaint->id])
        ->assertSuccessful()
        ->assertSee('foto-awal.jpg')
        ->assertSee('foto-kedua.jpg')
        ->assertSeeHtml(route('helpdesk.customer-complaints.attachments.show', ['path' => $paths[0]]))
        ->assertSeeHtml(route('helpdesk.customer-complaints.attachments.show', ['path' => $paths[1]]));
});

it('does not expose a follow-up action even to users holding the legacy update permission', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints', 'update customer complaints']);
    $complaint = CustomerComplaint::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($reviewer);
    Livewire::test(ViewCustomerComplaint::class, ['record' => $complaint->id])
        ->assertActionDoesNotExist('follow_up');

    expect($complaint->refresh()->getAttributes())->not->toHaveKey('status');
});

it('denies update and delete capabilities because the back office is recap-only', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $ownBranch->id]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints', 'update customer complaints']);

    $ownBranchComplaint = CustomerComplaint::factory()->create(['branch_id' => $ownBranch->id]);
    $otherBranchComplaint = CustomerComplaint::factory()->create(['branch_id' => $otherBranch->id, 'submitted_by' => $reviewer->id]);

    $this->actingAs($reviewer);

    expect(CustomerComplaintResource::canEdit($ownBranchComplaint))->toBeFalse()
        ->and(CustomerComplaintResource::canDelete($ownBranchComplaint))->toBeFalse()
        ->and(CustomerComplaintResource::canEdit($otherBranchComplaint))->toBeFalse()
        ->and(CustomerComplaintResource::canView($otherBranchComplaint))->toBeTrue();
});

it('does not grow the index query count as the number of complaints grows (no N+1)', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints']);
    $this->actingAs($reviewer);

    // A real Livewire-rendered request (not a direct $reviewer->can() call) is what actually
    // warms Spatie's role/permission cache for this code path; this throwaway render keeps that
    // one-time cost out of both measurements below so they isolate the table's own N+1 behavior.
    CustomerComplaint::factory()->create(['branch_id' => $branch->id]);
    Livewire::test(ListCustomerComplaints::class);

    // Both counts stay above the default page size (10) so the comparison isolates a true N+1
    // (which would scale with row count) from the one-off cost of crossing a pagination boundary.
    CustomerComplaint::factory()->count(11)->create(['branch_id' => $branch->id]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(ListCustomerComplaints::class);
    $smallCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    CustomerComplaint::factory()->count(14)->create(['branch_id' => $branch->id]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(ListCustomerComplaints::class);
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});

it('keeps the complaint unchanged when a reviewer only opens the recap detail', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints', 'update customer complaints']);
    $submitter = User::factory()->create();
    $complaint = CustomerComplaint::factory()->create(['branch_id' => $branch->id, 'submitted_by' => $submitter->id]);

    $this->actingAs($reviewer);
    Livewire::test(ViewCustomerComplaint::class, ['record' => $complaint->id])
        ->assertSuccessful()
        ->assertActionDoesNotExist('follow_up');

    expect($complaint->refresh()->getAttributes())->not->toHaveKeys([
        'status',
        'assigned_to', 'internal_notes', 'resolution', 'resolved_at',
        'resolved_by', 'closed_at', 'closed_by',
    ]);
});
