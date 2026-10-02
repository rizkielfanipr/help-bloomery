<?php

use App\Actions\StoreSalesOrder\CreateStoreSalesOrderAction;
use App\Actions\StoreSalesOrder\RefreshStoreSalesOrderSnapshotAction;
use App\Actions\StoreSalesOrder\UpdateStoreSalesOrderAction;
use App\Actions\StoreSalesOrder\UpdateStoreSalesOrderStatusAction;
use App\Enums\StoreSalesOrderStatus;
use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Models\StoreSalesOrder;
use App\Models\StoreSalesOrderActivity;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config()->set('esb.core.companies.BLSS', ['username' => 'sales-user', 'password' => 'sales-secret']);
});

/** @return array<string, mixed> */
function productSalesFixture(array $overrides = []): array
{
    return array_replace([
        'productSalesNum' => 'SL-00123',
        'productSalesDate' => '2026-10-01',
        'requiredDate' => '2026-10-05',
        'branchID' => 6,
        'branchName' => 'Bloomery Pabelan',
        'customerID' => 'CUST-01',
        'customerName' => 'Budi Santoso',
        'customerAddress' => 'Jl. Merdeka No. 1',
        'productSalesTotal' => 500000,
        'currencySign' => 'Rp',
        'statusID' => '1',
        'statusName' => 'Open',
        'createdBy' => 'admin.store',
        'linkPurchaseNum' => null,
        'additionalInfo' => null,
    ], $overrides);
}

function fakeProductSalesLookup(array $overrides = []): void
{
    Http::fake([
        'https://services.esb.co.id/core/sales/product-sales*' => Http::response(['status' => 'ok', 'result' => ['data' => [productSalesFixture($overrides)]]]),
        'https://services.esb.co.id/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]),
    ]);
}

/**
 * Http::fake() calls within a single test merge rather than replace (later registrations never
 * take precedence over an already-matching one), so a test that needs the ESB response to differ
 * between an initial lookup and a later refresh must register the whole sequence up front instead
 * of calling fakeProductSalesLookup() twice.
 *
 * @param  list<array<string, mixed>>  $resultDataByCall  one `result.data` payload per expected call, in order
 */
function fakeProductSalesSequence(array $resultDataByCall): void
{
    $sequence = Http::sequence();
    foreach ($resultDataByCall as $resultData) {
        $sequence->push(['status' => 'ok', 'result' => ['data' => $resultData]]);
    }

    Http::fake([
        'https://services.esb.co.id/core/sales/product-sales*' => $sequence,
        'https://services.esb.co.id/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]),
    ]);
}

/** @return array<string, mixed> */
function orderSubmissionData(int $branchId, array $overrides = []): array
{
    return array_merge([
        'branch_id' => $branchId,
        'product_sales_number' => 'SL-00123',
        'phone_number' => '0812xxxx',
        'ordered_by' => 'Budi',
        'event_type' => 'birthday',
        'event_type_other' => null,
        'delivery_time' => '10:00',
        'preparation_notes' => 'Siapkan lebih awal.',
        'attachment_paths' => null,
        'items' => [
            ['product_type' => 'db50_pack', 'custom_detail' => null, 'quantity' => 2, 'notes' => null],
        ],
    ], $overrides);
}

function branchWithEsbMapping(): Branch
{
    $branch = Branch::factory()->create();
    $mapping = BranchEsbCode::query()->create([
        'branch_id' => $branch->id,
        'esb_comcode' => 'BLSS',
        'esb_branch_id' => 6,
        'esb_branch_code' => 'PBL',
        'label' => 'NO LABEL',
        'is_active' => true,
    ]);
    $branch->update(['stock_card_esb_code_id' => $mapping->id]);

    return $branch->refresh();
}

it('creates a store sales order with a Submitted status and a created activity', function () {
    $branch = branchWithEsbMapping();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    fakeProductSalesLookup();

    $order = app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($branch->id), $user);

    expect($order->exists)->toBeTrue()
        ->and($order->operational_status)->toBe(StoreSalesOrderStatus::Submitted)
        ->and($order->product_sales_number)->toBe('SL-00123')
        ->and($order->customer_name_snapshot)->toBe('Budi Santoso')
        ->and($order->submitted_by)->toBe($user->id)
        ->and($order->items)->toHaveCount(1);

    $activity = StoreSalesOrderActivity::query()->where('store_sales_order_id', $order->id)->sole();
    expect($activity->activity_type)->toBe(StoreSalesOrderActivity::TYPE_CREATED)
        ->and($activity->new_status)->toBe(StoreSalesOrderStatus::Submitted->value);
});

it('rejects a submission for a branch the user cannot access', function () {
    $accessibleBranch = branchWithEsbMapping();
    $otherBranch = branchWithEsbMapping();
    $user = User::factory()->create(['branch_id' => $accessibleBranch->id]);
    fakeProductSalesLookup();

    expect(fn () => app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($otherBranch->id), $user))
        ->toThrow(ValidationException::class);

    expect(StoreSalesOrder::query()->count())->toBe(0);
});

it('rejects a submission when the branch has no active ESB mapping', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Http::fake();

    expect(fn () => app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($branch->id), $user))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
});

it('rejects a submission when ESB has no exact match for the number', function () {
    $branch = branchWithEsbMapping();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Http::fake([
        'https://services.esb.co.id/core/sales/product-sales*' => Http::response(['status' => 'ok', 'result' => ['data' => []]]),
        'https://services.esb.co.id/core/auth/login' => Http::response(['status' => 'ok', 'result' => ['accessToken' => 'fresh-token']]),
    ]);

    expect(fn () => app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($branch->id), $user))
        ->toThrow(ValidationException::class);

    expect(StoreSalesOrder::query()->count())->toBe(0);
});

it('redirects a duplicate submission to the existing record instead of creating a second one', function () {
    $branch = branchWithEsbMapping();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    fakeProductSalesLookup();

    $first = app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($branch->id), $user);
    $second = app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($branch->id), $user);

    expect($second->id)->toBe($first->id)
        ->and(StoreSalesOrder::query()->count())->toBe(1);
});

it('blocks a duplicate redirect when the user has no access to the existing record', function () {
    $branch = branchWithEsbMapping();
    $owner = User::factory()->create(['branch_id' => $branch->id]);
    fakeProductSalesLookup();
    app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($branch->id), $owner);

    $otherBranch = branchWithEsbMapping();
    $stranger = User::factory()->create(['branch_id' => $otherBranch->id]);
    // The stranger submits the exact same number for the same mapped branch (e.g. by guessing or
    // re-entering it) — PRD §14 still requires branch access to be re-validated first.
    expect(fn () => app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($branch->id), $stranger))
        ->toThrow(ValidationException::class);

    expect(StoreSalesOrder::query()->count())->toBe(1);
});

/**
 * True multi-connection concurrency (two separate, independently-committed transactions racing on
 * the unique index) cannot be exercised inside a single-process Pest run against an in-memory
 * SQLite database — any row inserted mid-flight via a model event lands inside the *same*
 * transaction CreateStoreSalesOrderAction opened, so it rolls back together with that transaction
 * the moment the real insert collides with it, leaving nothing for the catch block's
 * existing-record lookup to find. This mirrors the same documented limitation on
 * CreateCustomerComplaintAction's collision test, which sidesteps it differently (stubbing the
 * number generator) since that action has no pre-check query of its own to interfere with. Here,
 * the part of the catch block that *can* be proven without real concurrency — correctly
 * recognizing a genuine UniqueConstraintViolationException as this specific constraint, across both
 * MySQL's and SQLite's differently-shaped messages — is covered directly in
 * tests/Unit/CreateStoreSalesOrderActionConstraintMatchTest.php.
 */
it('updates operational information and replaces items, logging an info_updated activity', function () {
    $branch = branchWithEsbMapping();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    fakeProductSalesLookup();
    $order = app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($branch->id), $user);

    $updated = app(UpdateStoreSalesOrderAction::class)->execute($order, [
        'phone_number' => '0899999999',
        'ordered_by' => 'Siti',
        'event_type' => 'wedding',
        'event_type_other' => null,
        'delivery_time' => '08:00',
        'preparation_notes' => 'Diubah.',
        'attachment_paths' => null,
        'items' => [
            ['product_type' => 'custom', 'custom_detail' => 'Kue ulang tahun 2 tingkat', 'quantity' => 1, 'notes' => null],
        ],
    ], $user);

    expect($updated->phone_number)->toBe('0899999999')
        ->and($updated->ordered_by)->toBe('Siti')
        ->and($updated->updated_by)->toBe($user->id)
        ->and($updated->items)->toHaveCount(1)
        ->and($updated->items->first()->custom_detail)->toBe('Kue ulang tahun 2 tingkat');

    expect(StoreSalesOrderActivity::query()
        ->where('store_sales_order_id', $order->id)
        ->where('activity_type', StoreSalesOrderActivity::TYPE_INFO_UPDATED)
        ->exists())->toBeTrue();
});

it('accepts a valid status transition and rejects an invalid one', function () {
    $branch = branchWithEsbMapping();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    fakeProductSalesLookup();
    $order = app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($branch->id), $user);

    $action = app(UpdateStoreSalesOrderStatusAction::class);

    $inPreparation = $action->execute($order, StoreSalesOrderStatus::InPreparation, null, $user);
    expect($inPreparation->operational_status)->toBe(StoreSalesOrderStatus::InPreparation);

    expect(fn () => $action->execute($inPreparation, StoreSalesOrderStatus::Delivered, null, $user))
        ->toThrow(ValidationException::class);

    expect(StoreSalesOrderActivity::query()
        ->where('store_sales_order_id', $order->id)
        ->where('activity_type', StoreSalesOrderActivity::TYPE_STATUS_CHANGED)
        ->count())->toBe(1);
});

it('requires a cancellation reason when moving to Cancelled', function () {
    $branch = branchWithEsbMapping();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    fakeProductSalesLookup();
    $order = app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($branch->id), $user);

    $action = app(UpdateStoreSalesOrderStatusAction::class);

    expect(fn () => $action->execute($order, StoreSalesOrderStatus::Cancelled, null, $user))
        ->toThrow(ValidationException::class);

    $cancelled = $action->execute($order, StoreSalesOrderStatus::Cancelled, 'Customer membatalkan pesanan.', $user);
    expect($cancelled->operational_status)->toBe(StoreSalesOrderStatus::Cancelled)
        ->and($cancelled->cancellation_reason)->toBe('Customer membatalkan pesanan.');
});

it('refreshes only the ESB snapshot fields and last_verified_at, never operational data', function () {
    $branch = branchWithEsbMapping();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    fakeProductSalesSequence([
        [productSalesFixture()],
        [productSalesFixture(['customerName' => 'Customer Baru', 'productSalesTotal' => 750000])],
    ]);
    $order = app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($branch->id), $user);
    app(UpdateStoreSalesOrderStatusAction::class)->execute($order, StoreSalesOrderStatus::InPreparation, null, $user);
    $order->refresh();

    $refreshed = app(RefreshStoreSalesOrderSnapshotAction::class)->execute($order, $user);

    expect($refreshed->customer_name_snapshot)->toBe('Customer Baru')
        ->and((float) $refreshed->product_sales_total)->toBe(750.0 * 1000)
        ->and($refreshed->operational_status)->toBe(StoreSalesOrderStatus::InPreparation)
        ->and($refreshed->phone_number)->toBe('0812xxxx')
        ->and($refreshed->items)->toHaveCount(1);

    expect(StoreSalesOrderActivity::query()
        ->where('store_sales_order_id', $order->id)
        ->where('activity_type', StoreSalesOrderActivity::TYPE_SNAPSHOT_REFRESHED)
        ->exists())->toBeTrue();
});

it('leaves the old snapshot untouched when a refresh lookup fails to find the number', function () {
    $branch = branchWithEsbMapping();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    fakeProductSalesSequence([
        [productSalesFixture()],
        [],
    ]);
    $order = app(CreateStoreSalesOrderAction::class)->execute(orderSubmissionData($branch->id), $user);
    $originalCustomerName = $order->customer_name_snapshot;

    expect(fn () => app(RefreshStoreSalesOrderSnapshotAction::class)->execute($order, $user))
        ->toThrow(ValidationException::class);

    expect($order->refresh()->customer_name_snapshot)->toBe($originalCustomerName);
});
