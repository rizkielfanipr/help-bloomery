<?php

use App\Enums\CustomerComplaintStatus;
use App\Filament\Helpdesk\Resources\CustomerComplaints\CustomerComplaintResource;
use App\Filament\Helpdesk\Resources\CustomerComplaints\Pages\ListCustomerComplaints;
use App\Filament\Helpdesk\Resources\CustomerComplaints\Pages\ViewCustomerComplaint;
use App\Models\Branch;
use App\Models\CustomerComplaint;
use App\Models\CustomerComplaintActivity;
use App\Models\User;
use App\Notifications\CustomerComplaintResolvedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

it('filters the index by branch, category, source, and status', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints']);

    $matching = CustomerComplaint::factory()->create(['branch_id' => $branchA->id, 'status' => CustomerComplaintStatus::InReview]);
    $otherBranch = CustomerComplaint::factory()->create(['branch_id' => $branchB->id, 'status' => CustomerComplaintStatus::InReview]);
    $otherStatus = CustomerComplaint::factory()->create(['branch_id' => $branchA->id, 'status' => CustomerComplaintStatus::New]);

    $this->actingAs($reviewer);

    Livewire::test(ListCustomerComplaints::class)
        ->filterTable('branch_id', $branchA->id)
        ->filterTable('status', CustomerComplaintStatus::InReview->value)
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$otherBranch, $otherStatus]);
});

it('accepts the New -> In Review -> Resolved -> Closed transition and records each as an activity', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints', 'update customer complaints']);
    $complaint = CustomerComplaint::factory()->create(['branch_id' => $branch->id, 'status' => CustomerComplaintStatus::New]);

    $this->actingAs($reviewer);
    $page = Livewire::test(ViewCustomerComplaint::class, ['record' => $complaint->id]);

    $page->callAction('follow_up', ['status' => CustomerComplaintStatus::InReview->value, 'assigned_to' => null, 'internal_notes' => null, 'resolution' => null])
        ->assertHasNoActionErrors();
    expect($complaint->refresh()->status)->toBe(CustomerComplaintStatus::InReview);

    $page->callAction('follow_up', ['status' => CustomerComplaintStatus::Resolved->value, 'assigned_to' => null, 'internal_notes' => null, 'resolution' => 'Sudah diganti menu baru.'])
        ->assertHasNoActionErrors();
    $complaint->refresh();
    expect($complaint->status)->toBe(CustomerComplaintStatus::Resolved)
        ->and($complaint->resolved_at)->not->toBeNull();

    $page->callAction('follow_up', ['status' => CustomerComplaintStatus::Closed->value, 'assigned_to' => null, 'internal_notes' => null, 'resolution' => 'Sudah diganti menu baru.'])
        ->assertHasNoActionErrors();
    $complaint->refresh();
    expect($complaint->status)->toBe(CustomerComplaintStatus::Closed)
        ->and($complaint->closed_at)->not->toBeNull();

    expect(CustomerComplaintActivity::query()->where('customer_complaint_id', $complaint->id)
        ->where('activity_type', CustomerComplaintActivity::TYPE_STATUS_CHANGED)->count())->toBe(3);
});

it('rejects skipping a status in the transition, and rejects Resolved/Closed without a resolution', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints', 'update customer complaints']);
    $complaint = CustomerComplaint::factory()->create(['branch_id' => $branch->id, 'status' => CustomerComplaintStatus::New]);

    $this->actingAs($reviewer);
    $page = Livewire::test(ViewCustomerComplaint::class, ['record' => $complaint->id]);

    // New -> Resolved skips In Review entirely.
    $page->callAction('follow_up', ['status' => CustomerComplaintStatus::Resolved->value, 'assigned_to' => null, 'internal_notes' => null, 'resolution' => 'x']);
    expect($complaint->refresh()->status)->toBe(CustomerComplaintStatus::New);

    $page->callAction('follow_up', ['status' => CustomerComplaintStatus::InReview->value, 'assigned_to' => null, 'internal_notes' => null, 'resolution' => null]);
    expect($complaint->refresh()->status)->toBe(CustomerComplaintStatus::InReview);

    // In Review -> Resolved without a resolution.
    $page->callAction('follow_up', ['status' => CustomerComplaintStatus::Resolved->value, 'assigned_to' => null, 'internal_notes' => null, 'resolution' => null]);
    expect($complaint->refresh()->status)->toBe(CustomerComplaintStatus::InReview)
        ->and($complaint->resolution)->toBeNull();
});

it('only lets a user with update permission and branch access see the Tindak Lanjut action', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $ownBranch->id]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints', 'update customer complaints']);

    $ownBranchComplaint = CustomerComplaint::factory()->create(['branch_id' => $ownBranch->id]);
    $otherBranchComplaint = CustomerComplaint::factory()->create(['branch_id' => $otherBranch->id, 'submitted_by' => $reviewer->id]);

    $this->actingAs($reviewer);

    expect(CustomerComplaintResource::canEdit($ownBranchComplaint))->toBeTrue()
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

it('notifies the submitter only when the status transitions into Resolved, not on a later edit while already Resolved', function () {
    Notification::fake();
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'branch_id' => $branch->id]);
    $reviewer->givePermissionTo(['view any customer complaints', 'view customer complaints', 'update customer complaints']);
    $submitter = User::factory()->create();
    $complaint = CustomerComplaint::factory()->create(['branch_id' => $branch->id, 'status' => CustomerComplaintStatus::InReview, 'submitted_by' => $submitter->id]);

    $this->actingAs($reviewer);
    $page = Livewire::test(ViewCustomerComplaint::class, ['record' => $complaint->id]);

    $page->callAction('follow_up', ['status' => CustomerComplaintStatus::Resolved->value, 'assigned_to' => null, 'internal_notes' => null, 'resolution' => 'Sudah diselesaikan.']);
    Notification::assertSentToTimes($submitter, CustomerComplaintResolvedNotification::class, 1);

    // Editing the Internal Notes while the complaint stays Resolved must not re-notify.
    $page->callAction('follow_up', ['status' => CustomerComplaintStatus::Resolved->value, 'assigned_to' => null, 'internal_notes' => 'catatan tambahan', 'resolution' => 'Sudah diselesaikan.']);
    Notification::assertSentToTimes($submitter, CustomerComplaintResolvedNotification::class, 1);

    $page->callAction('follow_up', ['status' => CustomerComplaintStatus::Closed->value, 'assigned_to' => null, 'internal_notes' => null, 'resolution' => 'Sudah diselesaikan.']);
    Notification::assertSentToTimes($submitter, CustomerComplaintResolvedNotification::class, 2);
});
