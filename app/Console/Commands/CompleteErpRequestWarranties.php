<?php

namespace App\Console\Commands;

use App\Enums\ItRequestStatus;
use App\Models\ErpRepairRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('erp-requests:complete-warranty')]
#[Description('Complete IT requests whose three-business-day Warranty period has expired')]
class CompleteErpRequestWarranties extends Command
{
    public function handle(): int
    {
        $completed = 0;

        ErpRepairRequest::query()
            ->where('status', ItRequestStatus::Warranty->value)
            ->where('warranty_expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($requests) use (&$completed): void {
                foreach ($requests as $request) {
                    DB::transaction(function () use ($request, &$completed): void {
                        $locked = ErpRepairRequest::query()->lockForUpdate()->find($request->id);
                        if ($locked?->status !== ItRequestStatus::Warranty || $locked->warranty_expires_at?->isFuture()) {
                            return;
                        }

                        $locked->update(['status' => ItRequestStatus::Completed]);
                        $locked->activities()->create([
                            'actor_id' => null,
                            'action' => 'warranty_auto_completed',
                            'from_status' => ItRequestStatus::Warranty->value,
                            'to_status' => ItRequestStatus::Completed->value,
                            'notes' => 'Warranty berakhir tanpa klaim.',
                        ]);
                        $completed++;
                    });
                }
            });

        $this->info("Completed {$completed} IT request(s) after Warranty expired.");

        return self::SUCCESS;
    }
}
