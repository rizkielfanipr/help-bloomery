<?php

use App\Actions\UpdateErpRequestStatusAction;
use App\Enums\ItRequestStatus;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\EditErpRepairRequest;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ListErpRepairRequests;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages\ViewErpRepairRequest;
use App\Filament\Helpdesk\Widgets\ErpRequestSlaStatsWidget;
use App\Models\Branch;
use App\Models\ErpRepairRequest;
use App\Models\User;
use App\Services\ErpRequestSlaService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->itStaff = User::factory()->create(['is_active' => true]);
    $this->itStaff->assignRole('IT_STAFF');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($this->itStaff);
    $this->travelTo(CarbonImmutable::parse('2026-09-11 16:30:00', 'Asia/Jakarta'));
});

it('records the first response once and counts completion from submission across the weekend', function () {
    $request = ErpRepairRequest::factory()->create(['status' => ItRequestStatus::Submitted, 'resolved_at' => null]);
    $staleRequest = ErpRepairRequest::findOrFail($request->id);
    $this->travelTo(CarbonImmutable::parse('2026-09-14 09:30:00', 'Asia/Jakarta'));
    $page = Livewire::test(ViewErpRepairRequest::class, ['record' => $request->id]);

    expect($request->refresh()->first_responded_at)->toBeNull();
    $page->call('transitionTo', ItRequestStatus::Review->value)->assertHasNoErrors();
    expect($request->refresh()->response_business_seconds)->toBe(7200)
        ->and($request->first_responded_at->format('Y-m-d H:i:s'))->toBe('2026-09-14 09:30:00');

    $this->travelTo(CarbonImmutable::parse('2026-09-14 10:00:00', 'Asia/Jakarta'));
    app(UpdateErpRequestStatusAction::class)->execute($staleRequest, ItRequestStatus::Review, 'More notes', $this->itStaff);
    expect($request->refresh()->first_responded_at->format('H:i:s'))->toBe('09:30:00')
        ->and($request->response_business_seconds)->toBe(7200);

    $page->call('transitionTo', ItRequestStatus::Approved->value)->assertHasNoErrors()
        ->call('transitionTo', ItRequestStatus::Progress->value)->assertHasNoErrors();
    $this->travelTo(CarbonImmutable::parse('2026-09-15 09:00:00', 'Asia/Jakarta'));
    $page->call('transitionTo', ItRequestStatus::Completed->value)->assertHasNoErrors()
        ->assertSee('10 jam 30 menit')
        ->assertSee('11/09/2026')
        ->assertSee('14/09/2026')
        ->assertSee('15/09/2026')
        ->assertSee('Cara menghitung SLA ERP IT');

    expect($request->refresh()->resolution_business_seconds)->toBe(37800)
        ->and($request->closed_by)->toBe($this->itStaff->id)
        ->and($request->first_responded_at->format('H:i:s'))->toBe('09:30:00');

    $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'Asia/Jakarta'));
    app(UpdateErpRequestStatusAction::class)->execute($request, ItRequestStatus::Completed, 'Additional note', $this->itStaff);
    expect($request->refresh()->resolved_at->format('Y-m-d H:i:s'))->toBe('2026-09-15 09:00:00')
        ->and($request->resolution_business_seconds)->toBe(37800);
});

it('uses the same SLA tracking when editing a ticket', function () {
    $request = ErpRepairRequest::factory()->create(['status' => ItRequestStatus::Submitted, 'resolved_at' => null]);
    $this->travelTo(CarbonImmutable::parse('2026-09-14 08:30:00', 'Asia/Jakarta'));
    $page = Livewire::test(EditErpRepairRequest::class, ['record' => $request->id])
        ->fillForm(['status' => ItRequestStatus::Review->value, 'it_notes' => 'Checked'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($request->refresh()->response_business_seconds)->toBe(3600)
        ->and($request->activities()->where('action', 'status_changed')->count())->toBe(1);

    expect($page->instance()->getRecord()->status)->toBe(ItRequestStatus::Review);
});

it('does not stamp an SLA event when editing notes or skipping the workflow', function () {
    $request = ErpRepairRequest::factory()->create(['status' => ItRequestStatus::Submitted, 'resolved_at' => null]);
    $action = app(UpdateErpRequestStatusAction::class);
    $action->execute($request, ItRequestStatus::Submitted, 'Clarified issue', $this->itStaff);
    expect($request->refresh()->first_responded_at)->toBeNull()
        ->and($request->resolved_at)->toBeNull();

    expect(fn () => $action->execute($request, ItRequestStatus::Completed, null, $this->itStaff))->toThrow(ValidationException::class);
    expect($request->refresh()->first_responded_at)->toBeNull()
        ->and($request->status)->toBe(ItRequestStatus::Submitted);
});

it('keeps zero working seconds as a valid first response', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-12 10:00:00', 'Asia/Jakarta'));
    $request = ErpRepairRequest::factory()->create(['status' => ItRequestStatus::Submitted, 'resolved_at' => null]);
    $this->travelTo(CarbonImmutable::parse('2026-09-13 11:00:00', 'Asia/Jakarta'));
    Livewire::test(ViewErpRepairRequest::class, ['record' => $request->id])
        ->call('transitionTo', ItRequestStatus::Review->value)->assertHasNoErrors()
        ->assertSee('0 jam 0 menit')
        ->assertSee('Durasi 0 tetap valid');
    expect($request->refresh()->response_business_seconds)->toBe(0);
});

it('averages valid durations with independent samples and excludes deleted tickets', function () {
    $sla = app(ErpRequestSlaService::class);
    foreach ([
        [ItRequestStatus::Completed, '2026-09-14 08:00:00', '2026-09-14 08:00:00', '2026-09-14 09:00:00'],
        [ItRequestStatus::Rejected, '2026-09-14 08:00:00', '2026-09-14 10:00:00', null],
        [ItRequestStatus::Submitted, '2026-09-14 08:00:00', null, null],
        [ItRequestStatus::Completed, null, null, null],
    ] as [$status, $start, $response, $completion]) {
        $request = ErpRepairRequest::factory()->create(['status' => $status, 'submitted_at' => $start, 'first_responded_at' => $response, 'resolved_at' => $completion]);
        $sla->calculate($request);
        $request->saveQuietly();
    }
    $deleted = ErpRepairRequest::factory()->create(['status' => ItRequestStatus::Submitted, 'resolved_at' => null]);
    $deleted->delete();

    expect($sla->summary(ErpRepairRequest::query()))->toBe([
        'response_average' => 3600.0, 'response_count' => 2,
        'resolution_average' => 3600.0, 'resolution_count' => 1,
        'awaiting_response' => 1, 'unfinished' => 1,
    ]);
});

it('shows unavailable instead of a zero average when there are no valid samples', function () {
    expect(app(ErpRequestSlaService::class)->summary(ErpRepairRequest::query()))->toBe([
        'response_average' => null, 'response_count' => 0,
        'resolution_average' => null, 'resolution_count' => 0,
        'awaiting_response' => 0, 'unfinished' => 0,
    ]);
    Livewire::test(ErpRequestSlaStatsWidget::class)->assertSee('Tidak tersedia')->assertSee('0 tiket');
});

it('uses all filtered tickets rather than just one table page for the SLA widget', function () {
    $matching = ErpRepairRequest::factory()->count(12)->create(['status' => ItRequestStatus::Submitted, 'resolved_at' => null]);
    ErpRepairRequest::factory()->create(['status' => ItRequestStatus::Submitted, 'resolved_at' => null]);
    $one = $matching->first();

    $widget = Livewire::test(ErpRequestSlaStatsWidget::class);
    expect($widget->instance()->getStats()[2]['value'])->toBe('13');
    $filtered = Livewire::test(ErpRequestSlaStatsWidget::class, ['tableFilters' => ['ticket_number' => ['value' => $one->ticket_number]]]);
    expect($filtered->instance()->getStats()[2]['value'])->toBe('1');

    Livewire::test(ListErpRepairRequests::class)->assertSee('Rata-rata respons pertama')
        ->assertSee('RESPONS PERTAMA')->assertSee('PENYELESAIAN')
        ->assertSee('Cara menghitung SLA ERP IT');
});

it('filters SLA reports by submission date rather than an earlier creation date', function () {
    $request = ErpRepairRequest::factory()->create([
        'status' => ItRequestStatus::Review,
        'created_at' => '2026-09-10 12:00:00',
        'submitted_at' => '2026-09-14 08:00:00',
        'first_responded_at' => '2026-09-14 09:00:00',
        'response_business_seconds' => 3600,
    ]);
    Livewire::test(ListErpRepairRequests::class)
        ->set('tableFilters.created_at', ['from' => '2026-09-14', 'until' => '2026-09-14'])
        ->assertCanSeeTableRecords([$request]);
    $widget = Livewire::test(ErpRequestSlaStatsWidget::class, ['tableFilters' => ['created_at' => ['from' => '2026-09-14', 'until' => '2026-09-14']]]);
    expect($widget->instance()->getStats()[0]['value'])->toBe('1 jam 0 menit');
});

it('backfills recorded events without inventing missing history or modifying update times', function () {
    $request = ErpRepairRequest::factory()->create([
        'status' => ItRequestStatus::Completed,
        'created_at' => '2026-09-10 12:00:00', 'updated_at' => '2026-09-14 09:30:00', 'resolved_at' => null,
    ]);
    foreach ([
        ['submitted', null, 'submitted', '2026-09-11 16:30:00'],
        ['status_changed', 'submitted', 'in_review', '2026-09-14 08:30:00'],
        ['status_changed', 'in_progress', 'completed', '2026-09-14 09:30:00'],
    ] as [$action, $from, $to, $time]) {
        $activity = $request->activities()->create(['actor_id' => $this->itStaff->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to]);
        $activity->forceFill(['created_at' => $time])->saveQuietly();
    }
    $unknown = ErpRepairRequest::factory()->create(['status' => ItRequestStatus::Completed, 'resolved_at' => '2026-09-14 10:00:00']);

    $this->artisan('erp-requests:backfill-sla')->assertSuccessful();
    expect($request->refresh()->submitted_at->format('Y-m-d H:i:s'))->toBe('2026-09-11 16:30:00')
        ->and($request->response_business_seconds)->toBe(3600)
        ->and($request->resolution_business_seconds)->toBe(7200)
        ->and($request->updated_at->format('Y-m-d H:i:s'))->toBe('2026-09-14 09:30:00')
        ->and($unknown->refresh()->submitted_at)->toBeNull()
        ->and($unknown->response_business_seconds)->toBeNull()
        ->and($unknown->resolution_business_seconds)->toBeNull();
    $this->artisan('erp-requests:backfill-sla')->expectsOutputToContain('Updated 0 ERP requests.')->assertSuccessful();
});

it('averages every valid ticket in a filtered multi-page result', function () {
    $branch = Branch::factory()->create();
    ErpRepairRequest::factory()->count(10)->create([
        'status' => ItRequestStatus::Review, 'branch_id' => $branch->id,
        'submitted_at' => '2026-09-14 08:00:00', 'first_responded_at' => '2026-09-14 09:00:00',
        'response_business_seconds' => 3600,
    ]);
    ErpRepairRequest::factory()->count(2)->create([
        'status' => ItRequestStatus::Review, 'branch_id' => $branch->id,
        'submitted_at' => '2026-09-14 08:00:00', 'first_responded_at' => '2026-09-14 15:00:00',
        'response_business_seconds' => 25200,
    ]);
    ErpRepairRequest::factory()->create([
        'status' => ItRequestStatus::Review, 'branch_id' => null,
        'submitted_at' => '2026-09-14 08:00:00', 'first_responded_at' => '2026-09-14 16:00:00',
        'response_business_seconds' => 28800,
    ]);
    $widget = Livewire::test(ErpRequestSlaStatsWidget::class, ['tableFilters' => ['branch_id' => ['value' => $branch->id]]]);
    expect($widget->instance()->getStats()[0])->toBe([
        'label' => 'Rata-rata respons pertama', 'value' => '2 jam 0 menit',
        'description' => '12 tiket dengan durasi respons valid',
    ]);
});

it('keeps the SLA widget within existing ERP viewing permissions', function () {
    expect(ErpRequestSlaStatsWidget::canView())->toBeTrue();
    $otherUser = User::factory()->create(['is_active' => true]);
    $this->actingAs($otherUser);
    expect(ErpRequestSlaStatsWidget::canView())->toBeFalse();
});

it('does not invent a historical completion timestamp when updating a completed ticket note', function () {
    $request = ErpRepairRequest::factory()->create(['status' => ItRequestStatus::Completed, 'resolved_at' => null]);
    app(UpdateErpRequestStatusAction::class)->execute($request, ItRequestStatus::Completed, 'Updated note', $this->itStaff);

    expect($request->refresh()->resolved_at)->toBeNull()
        ->and($request->resolution_business_seconds)->toBeNull();
});
