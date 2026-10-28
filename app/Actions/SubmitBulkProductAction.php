<?php

namespace App\Actions;

use App\Models\BulkProductSubmission;
use App\Models\BulkProductSubmissionItem;
use App\Services\EsbCompanyProductService;
use App\Services\Monitoring\EsbTransactionRecorder;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubmitBulkProductAction
{
    public function __construct(
        private EsbCompanyProductService $esbProducts,
        private EsbTransactionRecorder $recorder,
    ) {}

    public function execute(BulkProductSubmission $submission): void
    {
        DB::transaction(function () use ($submission): void {
            $submission->update(['status' => 'processing', 'submitted_at' => now(), 'completed_at' => null]);

            foreach ($submission->target_comcodes as $comcode) {
                $submission->items()->firstOrCreate(['comcode' => $comcode], ['status' => 'pending']);
            }
        });

        $this->monitored($submission, function () use ($submission): void {
            foreach ($submission->items()->whereIn('status', ['pending', 'failed'])->get() as $item) {
                $this->processItem($item);
            }
        });

        $this->refreshSubmissionStatus($submission);
    }

    /**
     * Only an explicit `failed` target is retried; `unknown` and `succeeded` targets are never
     * sent again (docs/products/system-monitoring/prd.md §11.6).
     */
    public function retry(BulkProductSubmissionItem $item): void
    {
        abort_unless($item->status === 'failed', 422, 'Hanya target gagal yang dapat dicoba ulang.');
        $item->update(['status' => 'pending', 'error_message' => null, 'completed_at' => null]);
        $item->submission()->update(['status' => 'processing', 'completed_at' => null]);
        $this->monitored($item->submission, fn () => $this->processItem($item));
        $this->refreshSubmissionStatus($item->submission);
    }

    /**
     * One monitoring parent per submission; retries append attempts to it.
     */
    private function monitored(BulkProductSubmission $submission, Closure $callback): void
    {
        $this->recorder->resumeOrStart(
            $submission,
            'bulk_product',
            $submission->operation === 'update' ? 'submit_update' : 'submit_create',
            $submission->operation === 'update' ? 'update_product' : 'create_product',
            fn () => $callback(),
            ['idempotency_key' => 'bulk-product-submission:'.$submission->id, 'actor' => auth()->user()],
        );
    }

    private function processItem(BulkProductSubmissionItem $item): void
    {
        $submission = $item->submission;
        $payload = $this->payloadForComcode($submission->payload, $item->comcode, $submission->operation);
        $item->update([
            'status' => 'processing', 'request_payload' => $payload, 'response_payload' => null,
            'error_message' => null, 'attempts' => $item->attempts + 1,
            'started_at' => now(), 'completed_at' => null,
        ]);

        try {
            $result = $this->recorder->currentScope()?->forTarget($item->comcode, $item->comcode, fn (): array => $this->send($submission, $item, $payload))
                ?? $this->send($submission, $item, $payload);

            $item->update([
                'status' => 'succeeded', 'remote_product_id' => (int) $result['productID'],
                'response_payload' => $result, 'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $item->update([
                'status' => $this->isConnectionFailure($exception) ? 'unknown' : 'failed', 'error_message' => $exception->getMessage(),
                'response_payload' => ['message' => $exception->getMessage()], 'completed_at' => now(),
            ]);
        }
    }

    /** @return array{productID:int} */
    private function send(BulkProductSubmission $submission, BulkProductSubmissionItem $item, array $payload): array
    {
        if ($submission->operation !== 'update') {
            return $this->esbProducts->create($item->comcode, $payload);
        }

        // A changed conversion factor is replaced (old Product Detail deactivated, new one
        // created) exactly like R&D Product Release; an unchanged one is a plain update.
        $productId = (int) data_get($submission->remote_product_ids, $item->comcode, 0);
        $products = $this->esbProducts->masterProducts($item->comcode);
        $products->updateProductWithUnitReplacements($productId, $payload, $products->findProductById($productId) ?? []);

        return ['productID' => $productId];
    }

    private function payloadForComcode(array $payload, string $comcode, string $operation): array
    {
        $payload['productDetails'] = collect($payload['productDetails'] ?? [])
            ->map(function (array $detail) use ($comcode, $operation): array {
                if ($operation === 'update') {
                    $detail['productDetailID'] = data_get($detail, "productDetailIDs.{$comcode}");
                } else {
                    unset($detail['productDetailID']);
                }

                unset($detail['productDetailIDs'], $detail['uomName']);

                return $detail;
            })
            ->unique(fn (array $detail): int => (int) ($detail['uomID'] ?? 0))
            ->unique(function (array $detail, int $index): string {
                $sku = mb_strtoupper(trim((string) ($detail['sku'] ?? '')));

                return $sku !== '' ? 'sku:'.$sku : 'row:'.$index;
            })
            ->values()
            ->all();

        return $payload;
    }

    private function refreshSubmissionStatus(BulkProductSubmission $submission): void
    {
        $statuses = $submission->items()->pluck('status');
        $status = match (true) {
            $statuses->contains('unknown') => 'unknown',
            $statuses->every(fn (string $value): bool => $value === 'succeeded') => 'succeeded',
            $statuses->every(fn (string $value): bool => $value === 'failed') => 'failed',
            $statuses->contains('succeeded') && $statuses->contains('failed') => 'partial',
            default => 'processing',
        };

        $submission->update([
            'status' => $status,
            'completed_at' => in_array($status, ['succeeded', 'failed', 'partial', 'unknown'], true) ? now() : null,
        ]);
    }

    private function isConnectionFailure(Throwable $exception): bool
    {
        do {
            if ($exception instanceof ConnectionException) {
                return true;
            }

            $exception = $exception->getPrevious();
        } while ($exception instanceof Throwable);

        return false;
    }
}
