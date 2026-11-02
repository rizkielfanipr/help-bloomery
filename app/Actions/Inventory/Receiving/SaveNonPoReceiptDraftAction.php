<?php

namespace App\Actions\Inventory\Receiving;

use App\Enums\NonPoReceiptStatus;
use App\Models\NonPoReceipt;
use App\Models\NonPoReceiptItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Saves a local draft without touching ESB. A repeated submission key updates the same record, and
 * a stored photo path is kept only if this record already owns it or this save just uploaded it.
 */
class SaveNonPoReceiptDraftAction
{
    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $items  Items in the Receiving array shape with server-verified product fields.
     * @param  list<string>  $newPaths  Files stored by this save.
     */
    public function execute(User $actor, string $submissionKey, array $header, array $items, array $newPaths = []): NonPoReceipt
    {
        $this->assertUniqueIdentities($items);

        $removedPaths = [];
        $receipt = DB::transaction(function () use ($actor, $submissionKey, $header, $items, $newPaths, &$removedPaths): NonPoReceipt {
            $receipt = NonPoReceipt::query()->where('submission_key', $submissionKey)->lockForUpdate()->first();

            if ($receipt === null) {
                Gate::forUser($actor)->authorize('create', NonPoReceipt::class);
                $receipt = NonPoReceipt::query()->createOrFirst(['submission_key' => $submissionKey], [
                    'receipt_date' => $header['receipt_date'], 'created_by' => $actor->id,
                ]);
                if ($receipt->wasRecentlyCreated) {
                    $receipt->statusHistories()->create(['from_status' => null, 'to_status' => NonPoReceiptStatus::Draft, 'actor_id' => $actor->id]);
                }
                $receipt = NonPoReceipt::query()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();
            }

            if (! $receipt->status->canBeEditedByInventory()) {
                throw ValidationException::withMessages(['status' => 'Penerimaan sudah dikirim dan tidak dapat diubah.']);
            }

            Gate::forUser($actor)->authorize('update', $receipt);

            $receipt->load('items');
            $existingPaths = $receipt->storedPhotoPaths();
            $ownedPaths = array_merge($existingPaths, $newPaths);
            $owned = fn (array $paths): array => array_values(array_intersect($paths, $ownedPaths));

            $receipt->update(array_replace($header, [
                'document_photos' => $owned($header['document_photos']),
                'goods_photos' => $owned($header['goods_photos']),
            ]));

            $receipt->items()->delete();
            foreach ($items as $item) {
                $record = $receipt->items()->create([
                    'product_id' => $item['productID'], 'product_detail_id' => $item['productDetailID'],
                    'product_code' => $item['productCode'], 'product_name' => $item['productName'], 'uom_name' => $item['uomName'],
                    'physical_qty' => (float) $item['physicalQty'], 'accepted_qty' => (float) $item['acceptedQty'],
                    'hold_qty' => (float) $item['holdQty'], 'rejected_qty' => (float) $item['rejectedQty'],
                    'invoice_price' => filled($item['invoicePrice'] ?? null) ? (float) $item['invoicePrice'] : null,
                    'qc_data' => array_intersect_key($item, NonPoReceiptItem::defaultQcData()),
                    'evidence_photos' => $owned($item['storedEvidencePhotos'] ?? []),
                    'notes' => $item['notes'] ?? null,
                ]);
                foreach ($item['batches'] ?? [] as $batch) {
                    $record->batches()->create([
                        'batch_number' => $batch['batchNumber'] ?: null,
                        'manufactured_date' => $batch['manufacturedDate'] ?: null,
                        'expired_date' => $batch['expiredDate'] ?: null,
                        'quantity' => (float) $batch['quantity'], 'accepted_qty' => (float) $batch['acceptedQty'],
                        'hold_qty' => (float) $batch['holdQty'], 'rejected_qty' => (float) $batch['rejectedQty'],
                    ]);
                }
            }

            $removedPaths = array_values(array_diff($existingPaths, $receipt->load('items')->storedPhotoPaths()));

            return $receipt;
        });

        if ($removedPaths !== []) {
            Storage::disk('b2')->delete($removedPaths);
        }

        return $receipt;
    }

    /** @param list<array<string, mixed>> $items */
    private function assertUniqueIdentities(array $items): void
    {
        $identities = collect($items)->map(fn (array $item): string => $item['productDetailID'].'|'.NonPoReceiptItem::normalizeUom($item['uomName']));

        if ($identities->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Produk dengan Product Detail ID dan UOM yang sama tidak boleh dobel.']);
        }
    }
}
