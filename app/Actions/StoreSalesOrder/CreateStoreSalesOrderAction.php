<?php

namespace App\Actions\StoreSalesOrder;

use App\Enums\StoreSalesOrderStatus;
use App\Models\Branch;
use App\Models\StoreSalesOrder;
use App\Models\StoreSalesOrderActivity;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * docs/store-sales-order-prd.md §10, §14, §15.3. Branch is re-validated server-side (§16
 * "branch divalidasi ulang di server"). If the number is already stored for this
 * company+branch, the user is redirected to the existing record instead of creating a duplicate
 * (§14 "user diarahkan ke record yang ada jika mempunyai akses") — a second, DB-level unique
 * constraint is the final guard against a race between the duplicate check and the insert.
 *
 * @param  array{branch_id: int, product_sales_number: string, phone_number: ?string, ordered_by: ?string, event_type: ?string, event_type_other: ?string, delivery_time: ?string, preparation_notes: ?string, attachment_paths: ?array, items: list<array{product_type: string, custom_detail: ?string, quantity: float, notes: ?string}>}  $data
 */
class CreateStoreSalesOrderAction
{
    use MapsStoreSalesOrderSnapshot;

    public function __construct(private readonly LookupStoreSalesOrderAction $lookup) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data, User $actor): StoreSalesOrder
    {
        $branch = Branch::query()->findOrFail((int) $data['branch_id']);

        if (! $actor->canAccessBranch($branch->id)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Branch yang dipilih tidak dapat diakses.',
            ]);
        }

        $number = trim((string) $data['product_sales_number']);

        ['mapping' => $mapping, 'snapshot' => $snapshot] = $this->lookup->execute($branch, $number);

        $existing = StoreSalesOrder::query()
            ->where('company_code_snapshot', strtoupper(trim($mapping->esb_comcode)))
            ->where('esb_branch_id_snapshot', $mapping->esb_branch_id)
            ->where('product_sales_number', $snapshot['product_sales_number'])
            ->first();

        if ($existing) {
            if (! $actor->can('view', $existing)) {
                throw ValidationException::withMessages([
                    'product_sales_number' => 'Sales Order ini sudah tercatat, namun Anda tidak mempunyai akses melihatnya.',
                ]);
            }

            return $existing;
        }

        try {
            return DB::transaction(function () use ($branch, $mapping, $snapshot, $data, $actor): StoreSalesOrder {
                $order = StoreSalesOrder::query()->create([
                    ...$this->snapshotAttributes($branch, $mapping, $snapshot),
                    'phone_number' => filled($data['phone_number'] ?? null) ? trim((string) $data['phone_number']) : null,
                    'ordered_by' => filled($data['ordered_by'] ?? null) ? trim((string) $data['ordered_by']) : null,
                    'event_type' => $data['event_type'] ?? null,
                    'event_type_other' => filled($data['event_type_other'] ?? null) ? trim((string) $data['event_type_other']) : null,
                    'delivery_time' => $data['delivery_time'] ?? null,
                    'preparation_notes' => filled($data['preparation_notes'] ?? null) ? trim((string) $data['preparation_notes']) : null,
                    'attachment_paths' => $data['attachment_paths'] ?? null,
                    'operational_status' => StoreSalesOrderStatus::Submitted,
                    'submitted_by' => $actor->id,
                ]);

                foreach (($data['items'] ?? []) as $index => $item) {
                    $order->items()->create([
                        'product_type' => $item['product_type'],
                        'custom_detail' => filled($item['custom_detail'] ?? null) ? trim((string) $item['custom_detail']) : null,
                        'quantity' => $item['quantity'],
                        'notes' => filled($item['notes'] ?? null) ? trim((string) $item['notes']) : null,
                        'sort_order' => $index,
                    ]);
                }

                $order->activities()->create([
                    'activity_type' => StoreSalesOrderActivity::TYPE_CREATED,
                    'new_status' => StoreSalesOrderStatus::Submitted->value,
                    'created_by' => $actor->id,
                ]);

                return $order;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! self::isActiveNumberConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = StoreSalesOrder::query()
                ->where('company_code_snapshot', strtoupper(trim($mapping->esb_comcode)))
                ->where('esb_branch_id_snapshot', $mapping->esb_branch_id)
                ->where('product_sales_number', $snapshot['product_sales_number'])
                ->firstOrFail();

            if (! $actor->can('view', $existing)) {
                throw ValidationException::withMessages([
                    'product_sales_number' => 'Sales Order ini sudah tercatat, namun Anda tidak mempunyai akses melihatnya.',
                ]);
            }

            return $existing;
        }
    }

    /**
     * MySQL's duplicate-key message embeds the named constraint ("for key
     * 'store_sales_orders_active_number_unique'"); SQLite's embeds the raw column list instead
     * ("UNIQUE constraint failed: store_sales_orders.company_code_snapshot, ...,
     * product_sales_number_if_active"), never the index name — checking for either keeps this
     * portable between production (MySQL) and the test suite (SQLite) without depending on
     * driver-specific getIndex()/getColumns() population, which differs the same way (see
     * MySqlConnection::parseUniqueConstraintViolation() vs SQLiteConnection's).
     */
    public static function isActiveNumberConstraintViolation(UniqueConstraintViolationException $exception): bool
    {
        return str_contains($exception->getMessage(), 'store_sales_orders_active_number_unique')
            || str_contains($exception->getMessage(), 'product_sales_number_if_active');
    }
}
