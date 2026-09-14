<?php

namespace App\Console\Commands;

use App\Enums\ItRequestStatus;
use App\Models\ErpRepairRequest;
use App\Services\ErpRequestSlaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('erp-requests:backfill-sla')]
#[Description('Calculate weekday SLA durations from recorded ERP request activity timestamps')]
class BackfillErpRequestSla extends Command
{
    public function handle(ErpRequestSlaService $sla): int
    {
        $updated = 0;
        $missingSubmission = 0;
        $missingResponse = 0;

        ErpRepairRequest::withTrashed()->with('activities')->chunkById(250, function (Collection $requests) use ($sla, &$updated, &$missingSubmission, &$missingResponse): void {
            foreach ($requests as $historicalRequest) {
                DB::transaction(function () use ($historicalRequest, $sla, &$updated, &$missingSubmission, &$missingResponse): void {
                    $request = ErpRepairRequest::withTrashed()->lockForUpdate()->findOrFail($historicalRequest->getKey());
                    $activities = $historicalRequest->activities->sortBy('created_at');
                    $submission = $activities->first(fn ($activity): bool => $activity->action === 'submitted'
                        || ($activity->action === 'status_changed' && $activity->to_status === ItRequestStatus::Submitted->value));
                    $response = $activities->first(fn ($activity): bool => $activity->action === 'status_changed'
                        && $activity->from_status === ItRequestStatus::Submitted->value && $activity->to_status === ItRequestStatus::Review->value);
                    $completion = $activities->first(fn ($activity): bool => $activity->action === 'status_changed'
                        && $activity->to_status === ItRequestStatus::Completed->value);

                    $request->submitted_at ??= $submission?->created_at;
                    $request->first_responded_at ??= $response?->created_at;
                    if ($request->status === ItRequestStatus::Completed) {
                        $request->resolved_at ??= $completion?->created_at;
                    }

                    $sla->calculate($request);
                    if ($request->isDirty()) {
                        $request->timestamps = false;
                        $request->saveQuietly();
                        $updated++;
                    }

                    $missingSubmission += (int) ($request->submitted_at === null);
                    $missingResponse += (int) ($request->status !== ItRequestStatus::Submitted && $request->first_responded_at === null);
                });
            }
        });

        $this->info("Updated {$updated} ERP requests. Missing submission timestamps: {$missingSubmission}. Missing historical first responses: {$missingResponse}.");

        return self::SUCCESS;
    }
}
