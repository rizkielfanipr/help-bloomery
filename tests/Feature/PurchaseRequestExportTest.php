<?php

use App\Filament\Exports\PurchaseRequestExporter;
use App\Models\Branch;
use App\Models\PurchaseRequest;
use App\Models\User;

it('exports every purchase request when the date range is empty', function () {
    $user = User::factory()->create();
    $branch = Branch::factory()->create();

    PurchaseRequest::factory()->count(3)->create(['user_id' => $user->id, 'branch_id' => $branch->id]);

    $query = PurchaseRequestExporter::applyDateRange(PurchaseRequest::query(), []);

    expect($query->count())->toBe(3);
});

it('filters purchase requests using an inclusive date range', function () {
    $user = User::factory()->create();
    $branch = Branch::factory()->create();

    PurchaseRequest::factory()->create([
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'created_at' => '2026-08-31 23:59:59',
    ]);
    $included = PurchaseRequest::factory()->create([
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'created_at' => '2026-09-15 12:00:00',
    ]);
    PurchaseRequest::factory()->create([
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'created_at' => '2026-10-01 00:00:00',
    ]);

    $records = PurchaseRequestExporter::applyDateRange(PurchaseRequest::query(), [
        'date_from' => '2026-09-01',
        'date_until' => '2026-09-30',
    ])->get();

    expect($records)->toHaveCount(1)
        ->and($records->first()->is($included))->toBeTrue();
});

it('includes all purchase request columns and related names', function () {
    $columns = collect(PurchaseRequestExporter::getColumns())->map->getName()->all();

    expect($columns)->toBe([
        'id',
        'code',
        'purchase_request_number',
        'journal_item_number',
        'created_at',
        'updated_at',
        'user_id',
        'user.name',
        'user.username',
        'branch_id',
        'branch.name',
        'division',
        'item_name',
        'quantity',
        'purchase_type',
        'purchase_reason',
        'ecommerce_link',
        'attachment_paths',
        'status',
        'admin_notes',
        'processed_by',
        'processedBy.name',
    ]);
});
