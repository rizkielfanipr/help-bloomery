<?php

namespace App\Actions\Purchasing\VendorCompliance;

use App\Enums\DeliveryAssessmentStatus;
use App\Enums\VendorComplianceLiabilityStatus;
use App\Models\GoodsReceipt;
use App\Models\VendorComplianceIncident;
use App\Services\Purchasing\VendorCompliance\LateDeliveryScoring;
use App\Services\Purchasing\VendorCompliance\VendorComplianceNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates the one Late Delivery candidate per company + PO for a receipt assessed `late`
 * (PRD §11.9-16). It runs inside the Receiving transaction, makes no network call, and never touches
 * the ESB payload. The unique `source_key` plus `createOrFirst` and a row lock keep double submits and
 * concurrent partial receipts on a single incident; points stay 0 until Purchasing confirms.
 */
class AssessLateDeliveryComplianceAction
{
    public function __construct(
        private readonly LateDeliveryScoring $scoring,
        private readonly VendorComplianceNotifier $notifier,
    ) {}

    public function execute(GoodsReceipt $receipt, ?int $actorId): ?VendorComplianceIncident
    {
        if ($receipt->delivery_assessment_status !== DeliveryAssessmentStatus::Late || ! $receipt->delivery_delay_days) {
            return null;
        }

        return DB::transaction(function () use ($receipt, $actorId): VendorComplianceIncident {
            $delay = (int) $receipt->delivery_delay_days;
            $receivedOn = $receipt->goods_receipt_date->toDateString();
            $requiredOn = $receipt->po_required_date?->toDateString();
            $preview = $this->scoring->score($delay);

            $incident = VendorComplianceIncident::query()->createOrFirst(
                ['source_key' => VendorComplianceIncident::lateDeliverySourceKey($receipt->company_code, $receipt->reference_number)],
                [
                    'goods_receipt_id' => $receipt->id,
                    'goods_receipt_item_id' => null,
                    'supplier_id' => $receipt->supplier_id,
                    'supplier_name' => $receipt->supplier_name,
                    'category' => VendorComplianceIncident::CATEGORY_LATE_DELIVERY,
                    'source_type' => VendorComplianceIncident::CATEGORY_LATE_DELIVERY,
                    'severity' => $preview['severity'],
                    'demerit_points' => 0,
                    'affected_quantity' => null,
                    'status' => 'open',
                    'liability_status' => VendorComplianceLiabilityStatus::Pending,
                    'required_date' => $requiredOn,
                    'first_late_receipt_date' => $receivedOn,
                    'latest_late_receipt_date' => $receivedOn,
                    'delay_days' => $delay,
                    'description' => "Penerimaan PO {$receipt->reference_number} pada {$receivedOn} terdeteksi {$delay} hari setelah tanggal dibutuhkan {$requiredOn}.",
                    'evidence_photos' => [],
                    'reported_by' => $actorId,
                    'occurred_at' => now(),
                ],
            );

            if ($incident->wasRecentlyCreated) {
                $incident->events()->create([
                    'event' => 'candidate_created',
                    'actor_id' => $actorId,
                    'to_liability_status' => VendorComplianceLiabilityStatus::Pending,
                    'metadata' => $this->receiptMetadata($receipt),
                ]);
                $this->notifier->lateDeliveryCandidate($incident);

                return $incident;
            }

            return $this->recordPartialReceipt($incident, $receipt, $actorId);
        });
    }

    /**
     * A later receipt for the same PO: keep the latest late date and the maximum delay. After a final
     * liability decision the delay, severity, and points stay as decided; the receipt is only recorded.
     */
    private function recordPartialReceipt(VendorComplianceIncident $incident, GoodsReceipt $receipt, ?int $actorId): VendorComplianceIncident
    {
        $locked = VendorComplianceIncident::query()->whereKey($incident->getKey())->lockForUpdate()->firstOrFail();
        $alreadyRecorded = $locked->events()->where('metadata->goods_receipt_id', $receipt->id)->exists();
        if ($alreadyRecorded) {
            return $locked;
        }

        $delay = (int) $receipt->delivery_delay_days;
        $receivedOn = $receipt->goods_receipt_date->toDateString();
        $changes = [];
        if ($locked->latest_late_receipt_date === null || $receivedOn > $locked->latest_late_receipt_date->toDateString()) {
            $changes['latest_late_receipt_date'] = $receivedOn;
        }
        if (! $locked->liability_status->isDecided() && $delay > (int) $locked->delay_days) {
            $changes['delay_days'] = $delay;
            $changes['severity'] = $this->scoring->score($delay)['severity'];
        }
        if ($changes !== []) {
            $locked->forceFill($changes)->save();
        }

        $locked->events()->create([
            'event' => 'delay_updated',
            'actor_id' => $actorId,
            'from_liability_status' => $locked->liability_status,
            'to_liability_status' => $locked->liability_status,
            'metadata' => $this->receiptMetadata($receipt) + ['max_delay_days' => (int) $locked->delay_days],
        ]);

        return $locked;
    }

    /** @return array<string, mixed> */
    private function receiptMetadata(GoodsReceipt $receipt): array
    {
        return [
            'goods_receipt_id' => $receipt->id,
            'goods_receipt_date' => $receipt->goods_receipt_date->toDateString(),
            'po_required_date' => $receipt->po_required_date?->toDateString(),
            'delay_days' => (int) $receipt->delivery_delay_days,
        ];
    }
}
