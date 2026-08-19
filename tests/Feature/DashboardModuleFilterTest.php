<?php

use App\Enums\ServiceRequestStatus;
use App\Filament\Helpdesk\Pages\Dashboard;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Models\Branch;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Role::findOrCreate('technician', 'web');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->admin->assignRole('SUPERADMIN');
    actingAs($this->admin);
});

it('builds dashboard metric links with active and completed table filters', function () {
    $stats = Livewire::test(Dashboard::class)->get('moduleStats');
    $service = collect($stats)->firstWhere('key', 'service');

    parse_str((string) parse_url($service['pending_href'], PHP_URL_QUERY), $pendingQuery);
    parse_str((string) parse_url($service['completed_href'], PHP_URL_QUERY), $completedQuery);

    expect($pendingQuery['filters']['status']['values'])->toBe([
        ServiceRequestStatus::Submitted->value,
        ServiceRequestStatus::InProgress->value,
        ServiceRequestStatus::ReSubmitted->value,
    ])->and($completedQuery['filters']['status']['values'])->toBe([
        ServiceRequestStatus::Completed->value,
    ]);
});

it('opens the service request index with active records already filtered', function () {
    $branch = Branch::factory()->create();
    $active = ServiceRequest::factory()->create([
        'branch_id' => $branch->id,
        'scheduled_by' => $this->admin->id,
        'status' => ServiceRequestStatus::InProgress,
    ]);
    $completed = ServiceRequest::factory()->create([
        'branch_id' => $branch->id,
        'scheduled_by' => $this->admin->id,
        'status' => ServiceRequestStatus::Completed,
    ]);

    Livewire::withQueryParams([
        'filters' => [
            'status' => ['values' => [
                ServiceRequestStatus::Submitted->value,
                ServiceRequestStatus::InProgress->value,
                ServiceRequestStatus::ReSubmitted->value,
            ]],
        ],
    ])->test(ListServiceRequests::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$completed]);
});
