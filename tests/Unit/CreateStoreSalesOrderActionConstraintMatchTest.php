<?php

use App\Actions\StoreSalesOrder\CreateStoreSalesOrderAction;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

uses(TestCase::class);

/**
 * docs/store-sales-order-prd.md §14 "Submit berulang tidak boleh membuat dua record": the DB-level
 * unique constraint is the final guard behind CreateStoreSalesOrderAction's own pre-check, for the
 * race window between the two. True multi-connection concurrency can't be exercised against a
 * single-process, in-memory SQLite test database (any row inserted mid-flight via a model event
 * rolls back together with the very transaction it was meant to outlive), so this instead proves
 * the one thing that previously broke silently: MySQL's duplicate-key message embeds the named
 * constraint, but SQLite's embeds the raw column list instead — a check written for only one shape
 * passes in production and fails the moment the test suite runs the same code path against SQLite.
 */
function fakeUniqueConstraintViolation(string $driverMessage): UniqueConstraintViolationException
{
    return new UniqueConstraintViolationException(
        'testing',
        'insert into "store_sales_orders" (...) values (...)',
        [],
        new RuntimeException($driverMessage),
    );
}

it('recognizes MySQL\'s named-constraint message', function () {
    $exception = fakeUniqueConstraintViolation(
        "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'BLSS-6-SL-00123' for key 'store_sales_orders.store_sales_orders_active_number_unique'",
    );

    expect(CreateStoreSalesOrderAction::isActiveNumberConstraintViolation($exception))->toBeTrue();
});

it('recognizes SQLite\'s raw column-list message', function () {
    $exception = fakeUniqueConstraintViolation(
        'UNIQUE constraint failed: store_sales_orders.company_code_snapshot, store_sales_orders.esb_branch_id_snapshot, store_sales_orders.product_sales_number_if_active',
    );

    expect(CreateStoreSalesOrderAction::isActiveNumberConstraintViolation($exception))->toBeTrue();
});

it('does not misclassify a collision on an unrelated constraint', function () {
    $exception = fakeUniqueConstraintViolation(
        "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'x' for key 'users.users_email_unique'",
    );

    expect(CreateStoreSalesOrderAction::isActiveNumberConstraintViolation($exception))->toBeFalse();
});
