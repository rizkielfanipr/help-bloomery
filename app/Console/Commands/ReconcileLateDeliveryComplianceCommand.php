<?php

namespace App\Console\Commands;

use App\Enums\DeliveryAssessmentStatus;
use App\Enums\VendorComplianceLiabilityStatus;
use App\Models\GoodsReceipt;
use App\Models\VendorComplianceIncident;
use Illuminate\Console\Command;

/**
 * Read-only check after rollout (docs/products/purchasing/vendor-compliance-late-delivery/prd.md §19.5-6):
 * late receipts without a candidate, Late Delivery incidents without a source key, and the review
 * queue. It never writes; any repair needs a separate requirement. Output is aggregate only.
 */
class ReconcileLateDeliveryComplianceCommand extends Command
{
    protected $signature = 'vendor-compliance:reconcile-late-delivery';

    protected $description = 'Report late receipts without a Late Delivery candidate and the liability review queue. Read-only.';

    public function handle(): int
    {
        $lateReceipts = GoodsReceipt::query()
            ->where('delivery_assessment_status', DeliveryAssessmentStatus::Late->value)
            ->get(['company_code', 'reference_number']);
        $sourceKeys = $lateReceipts
            ->map(fn (GoodsReceipt $receipt): string => VendorComplianceIncident::lateDeliverySourceKey((string) $receipt->company_code, (string) $receipt->reference_number))
            ->unique();
        $existingKeys = VendorComplianceIncident::query()->whereIn('source_key', $sourceKeys->all())->pluck('source_key');
        $missing = $sourceKeys->diff($existingKeys)->count();

        $late = VendorComplianceIncident::query()->where('category', VendorComplianceIncident::CATEGORY_LATE_DELIVERY);
        $oldestPending = (clone $late)->where('liability_status', VendorComplianceLiabilityStatus::Pending->value)->min('occurred_at');
        $withoutSourceKey = (clone $late)->whereNull('source_key')->count();

        $this->table(['Pemeriksaan', 'Jumlah'], [
            ['Goods Receipt terlambat', $lateReceipts->count()],
            ['PO terlambat tanpa kandidat Late Delivery', $missing],
            ['Insiden Late Delivery tanpa source key', $withoutSourceKey],
            ['Menunggu verifikasi', (clone $late)->where('liability_status', VendorComplianceLiabilityStatus::Pending->value)->count()],
            ['Dikonfirmasi', (clone $late)->where('liability_status', VendorComplianceLiabilityStatus::Confirmed->value)->count()],
            ['Dikecualikan', (clone $late)->where('liability_status', VendorComplianceLiabilityStatus::Waived->value)->count()],
        ]);
        $this->line('Kandidat menunggu tertua: '.($oldestPending ? now()->diffForHumans($oldestPending, true).' lalu' : '-'));

        return $missing > 0 || $withoutSourceKey > 0 ? self::FAILURE : self::SUCCESS;
    }
}
