<?php

namespace App\Actions;

use App\Models\BulkProductSubmission;
use App\Models\BulkProductSubmissionItem;
use App\Services\EsbCompanyProductService;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubmitBulkProductAction
{
    public function __construct(private EsbCompanyProductService $esbProducts) {}

    public function execute(BulkProductSubmission $submission): void
    {
        DB::transaction(function () use ($submission): void {
            $submission->update(['status' => 'processing', 'submitted_at' => now(), 'completed_at' => null]);

            foreach ($submission->target_comcodes as $comcode) {
                $submission->items()->firstOrCreate(['comcode' => $comcode], ['status' => 'pending']);
            }
        });

        foreach ($submission->items()->whereIn('status', ['pending', 'failed'])->get() as $item) {
            $this->processItem($item);
        }

        $this->refreshSubmissionStatus($submission);
    }

    public function retry(BulkProductSubmissionItem $item): void
    {
        abort_unless($item->status === 'failed', 422, 'Hanya target gagal yang dapat dicoba ulang.');
        $item->update(['status' => 'pending', 'error_message' => null, 'completed_at' => null]);
        $item->submission()->update(['status' => 'processing', 'completed_at' => null]);
        $this->processItem($item);
        $this->refreshSubmissionStatus($item->submission);
    }

    private function processItem(BulkProductSubmissionItem $item): void
    {
        $submission = $item->submission;
        $payload = $this->payloadForComcode($submission->payload, $item->comcode);
        $item->update([
            'status' => 'processing', 'request_payload' => $payload, 'response_payload' => null,
            'error_message' => null, 'attempts' => $item->attempts + 1,
            'started_at' => now(), 'completed_at' => null,
        ]);

        try {
            if ($submission->operation === 'update') {
                $productId = (int) data_get($submission->remote_product_ids, $item->comcode, 0);
                $this->esbProducts->update($item->comcode, $productId, $payload);
                $result = ['productID' => $productId];
            } else {
                $result = $this->esbProducts->create($item->comcode, $payload);
            }

            $item->update([
                'status' => 'succeeded', 'remote_product_id' => (int) $result['productID'],
                'response_payload' => $result, 'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $item->update([
                'status' => 'failed', 'error_message' => $exception->getMessage(),
                'response_payload' => ['message' => $exception->getMessage()], 'completed_at' => now(),
            ]);
        }
    }

    private function payloadForComcode(array $payload, string $comcode): array
    {
        $payload['productDetails'] = collect($payload['productDetails'] ?? [])->map(function (array $detail) use ($comcode): array {
            $detail['productDetailID'] = data_get($detail, "productDetailIDs.{$comcode}");
            unset($detail['productDetailIDs'], $detail['uomName']);

            return $detail;
        })->all();

        return $payload;
    }

    private function refreshSubmissionStatus(BulkProductSubmission $submission): void
    {
        $statuses = $submission->items()->pluck('status');
        $status = match (true) {
            $statuses->every(fn (string $value): bool => $value === 'succeeded') => 'succeeded',
            $statuses->every(fn (string $value): bool => $value === 'failed') => 'failed',
            $statuses->contains('succeeded') && $statuses->contains('failed') => 'partial',
            default => 'processing',
        };

        $submission->update([
            'status' => $status,
            'completed_at' => in_array($status, ['succeeded', 'failed', 'partial'], true) ? now() : null,
        ]);
    }
}
