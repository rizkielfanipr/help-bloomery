<?php

use App\Enums\MonitoringTransactionStatus;
use App\Filament\Helpdesk\Resources\MonitoringTransactions\MonitoringTransactionResource;
use App\Filament\Helpdesk\Resources\MonitoringTransactions\Pages\ListMonitoringTransactions;
use App\Filament\Helpdesk\Resources\MonitoringTransactions\Pages\ViewMonitoringTransaction;
use App\Models\Branch;
use App\Models\MonitoringTransaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
});

function monitoringUser(array $permissions, array $attributes = ['access_all_branches' => true]): User
{
    $user = User::factory()->create(['is_active' => true] + $attributes);
    $user->givePermissionTo(['access backoffice', ...$permissions]);

    return $user;
}

function transactionWithAttempt(array $attributes = []): MonitoringTransaction
{
    $parent = MonitoringTransaction::factory()->create($attributes + [
        'status' => MonitoringTransactionStatus::Unknown,
        'external_reference' => 'IU-REF-1',
    ]);
    MonitoringTransaction::factory()->attemptOf($parent)->create([
        'status' => MonitoringTransactionStatus::Unknown,
        'request_summary' => ['itemJournalDate' => '2026-10-20', 'branchID' => 373],
        'response_summary' => ['message' => 'SENSITIVE-SUMMARY-MARKER'],
        'error_code' => 'connection_lost',
        'safe_error_message' => 'connection reset after send',
    ]);

    return $parent;
}

it('blocks users without monitoring permissions from the index and detail routes', function () {
    $transaction = transactionWithAttempt();
    $this->actingAs(monitoringUser([]));

    $this->get(MonitoringTransactionResource::getUrl('index'))->assertForbidden();
    $this->get(MonitoringTransactionResource::getUrl('view', ['record' => $transaction]))->assertForbidden();
});

it('keeps Management (overview only) out of the technical transaction monitor', function () {
    $this->actingAs(monitoringUser(['view system monitoring']));

    $this->get(MonitoringTransactionResource::getUrl('index'))->assertForbidden();
});

it('lists parents only, paginated, without loading payload columns', function () {
    transactionWithAttempt();
    MonitoringTransaction::factory()->count(3)->create(['source_module' => 'goods_receipt']);
    $this->actingAs(monitoringUser(['view system monitoring', 'view monitoring technical details']));

    $component = Livewire::test(ListMonitoringTransactions::class)
        ->assertCanSeeTableRecords(MonitoringTransaction::query()->parents()->get())
        ->assertCountTableRecords(4);

    $records = $component->instance()->getTableRecords();
    expect($records->first()->getAttributes())->not->toHaveKey('request_summary')->not->toHaveKey('response_summary');
    Http::assertNothingSent();
});

it('filters by status, module, and correlation id search', function () {
    $unknown = transactionWithAttempt();
    $succeeded = MonitoringTransaction::factory()->create(['source_module' => 'goods_receipt', 'status' => MonitoringTransactionStatus::Succeeded]);
    $this->actingAs(monitoringUser(['view system monitoring', 'view monitoring technical details']));

    Livewire::test(ListMonitoringTransactions::class)
        ->filterTable('status', ['unknown'])
        ->assertCanSeeTableRecords([$unknown])
        ->assertCanNotSeeTableRecords([$succeeded])
        ->resetTableFilters()
        ->filterTable('source_module', 'goods_receipt')
        ->assertCanSeeTableRecords([$succeeded])
        ->assertCanNotSeeTableRecords([$unknown])
        ->resetTableFilters()
        ->searchTable($succeeded->correlation_id)
        ->assertCanSeeTableRecords([$succeeded])
        ->assertCanNotSeeTableRecords([$unknown]);
});

it('hides sanitized payload summaries from users without the payload permission', function () {
    $transaction = transactionWithAttempt();
    $this->actingAs(monitoringUser(['view system monitoring', 'view monitoring technical details']));

    $this->get(MonitoringTransactionResource::getUrl('view', ['record' => $transaction]))
        ->assertOk()
        ->assertSee('Timeline Attempt')
        ->assertSee('connection reset after send')
        ->assertSee('Rekonsiliasi dengan ESB terlebih dahulu')
        ->assertDontSee('SENSITIVE-SUMMARY-MARKER');
});

it('shows sanitized payload summaries to users with the payload permission', function () {
    $transaction = transactionWithAttempt();
    $this->actingAs(monitoringUser(['view system monitoring', 'view monitoring technical details', 'view monitoring sanitized payloads']));

    $this->get(MonitoringTransactionResource::getUrl('view', ['record' => $transaction]))
        ->assertOk()
        ->assertSee('SENSITIVE-SUMMARY-MARKER');
});

it('scopes transactions to accessible branches for branch-limited users', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $visible = MonitoringTransaction::factory()->create(['branch_id' => $ownBranch->id]);
    $hidden = MonitoringTransaction::factory()->create(['branch_id' => $otherBranch->id]);
    $user = monitoringUser(['view system monitoring', 'view monitoring technical details'], ['access_all_branches' => false]);
    $user->syncBranchAccess([$ownBranch->id], $ownBranch->id);
    $this->actingAs($user);

    Livewire::test(ListMonitoringTransactions::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);
    $this->get(MonitoringTransactionResource::getUrl('view', ['record' => $hidden]))->assertNotFound();
});

it('records a manual reconciliation only for users with the reconcile permission', function () {
    $transaction = transactionWithAttempt();
    $viewer = monitoringUser(['view system monitoring', 'view monitoring technical details']);
    $this->actingAs($viewer);
    Livewire::test(ViewMonitoringTransaction::class, ['record' => $transaction->uuid])->assertActionHidden('recordReconciliation');

    $reconciler = monitoringUser(['view system monitoring', 'view monitoring technical details', 'reconcile esb transactions']);
    $this->actingAs($reconciler);
    Livewire::test(ViewMonitoringTransaction::class, ['record' => $transaction->uuid])
        ->callAction('recordReconciliation', ['outcome' => 'confirmed_applied', 'note' => 'IU-REF-1 sudah ada di ESB'])
        ->assertHasNoActionErrors();

    expect($transaction->refresh()->status)->toBe(MonitoringTransactionStatus::Reconciled)
        ->and($transaction->reconciliation_outcome)->toBe('confirmed_applied')
        ->and($transaction->reconciled_by)->toBe($reconciler->id);
});

it('grants IT staff the technical monitoring permissions but not settings', function () {
    $role = Role::findByName('IT_STAFF');

    expect($role->hasPermissionTo('view monitoring technical details'))->toBeTrue()
        ->and($role->hasPermissionTo('reconcile esb transactions'))->toBeTrue()
        ->and($role->hasPermissionTo('manage monitoring settings'))->toBeFalse();
});
