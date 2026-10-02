<?php

namespace App\Actions\StoreSalesOrder;

use App\Enums\StoreSalesOrderProductType;
use App\Models\StoreSalesOrder;
use App\Models\StoreSalesOrderActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * docs/store-sales-order-prd.md §10.2, §10.3, §13.3, §16 ("update store sales orders" governs
 * operational information and Kebutuhan Produk only — never the ESB snapshot or operational
 * status). Items are replaced wholesale inside the same transaction since a full edit always
 * resends the full list (matching how the user-facing form repeater works, §10.3 "menambah atau
 * mengurangi baris kapan pun").
 *
 * @param  array{phone_number: ?string, ordered_by: ?string, event_type: ?string, event_type_other: ?string, delivery_time: ?string, preparation_notes: ?string, attachment_paths: ?array, items: list<array{product_type: string, custom_detail: ?string, quantity: float, notes: ?string}>}  $data
 */
class UpdateStoreSalesOrderAction
{
    /** @param array<string, mixed> $data */
    public function execute(StoreSalesOrder $order, array $data, User $actor): StoreSalesOrder
    {
        foreach (($data['items'] ?? []) as $index => $item) {
            $productType = $item['product_type'] instanceof StoreSalesOrderProductType ? $item['product_type'] : StoreSalesOrderProductType::from($item['product_type']);

            if ($productType->requiresCustomDetail() && blank($item['custom_detail'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.{$index}.custom_detail" => 'Detail Custom wajib diisi.',
                ]);
            }
        }

        return DB::transaction(function () use ($order, $data, $actor): StoreSalesOrder {
            $order->update([
                'phone_number' => filled($data['phone_number'] ?? null) ? trim((string) $data['phone_number']) : null,
                'ordered_by' => filled($data['ordered_by'] ?? null) ? trim((string) $data['ordered_by']) : null,
                'event_type' => $data['event_type'] ?? null,
                'event_type_other' => filled($data['event_type_other'] ?? null) ? trim((string) $data['event_type_other']) : null,
                'delivery_time' => $data['delivery_time'] ?? null,
                'preparation_notes' => filled($data['preparation_notes'] ?? null) ? trim((string) $data['preparation_notes']) : null,
                'attachment_paths' => $data['attachment_paths'] ?? null,
                'updated_by' => $actor->id,
            ]);

            $order->items()->delete();

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
                'activity_type' => StoreSalesOrderActivity::TYPE_INFO_UPDATED,
                'created_by' => $actor->id,
            ]);

            return $order->refresh();
        });
    }
}
