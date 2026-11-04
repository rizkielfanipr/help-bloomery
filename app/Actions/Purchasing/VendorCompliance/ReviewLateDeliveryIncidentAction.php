<?php

namespace App\Actions\Purchasing\VendorCompliance;

use App\Enums\VendorComplianceLiabilityStatus;
use App\Models\User;
use App\Models\VendorComplianceIncident;
use App\Services\Purchasing\VendorCompliance\LateDeliveryScoring;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Purchasing decides a pending Late Delivery candidate (PRD §11.17-19, §26.1 no. 4-5). Confirming
 * activates severity and points from the versioned matrix; waiving needs a reason, keeps 0 points,
 * and closes the handling status. Both decisions are final in v1 and recorded in the event log.
 */
class ReviewLateDeliveryIncidentAction
{
    public function __construct(private readonly LateDeliveryScoring $scoring) {}

    /** @throws AuthorizationException|ValidationException */
    public function confirm(User $actor, VendorComplianceIncident $incident): VendorComplianceIncident
    {
        return $this->decide($actor, $incident, function (VendorComplianceIncident $locked) use ($actor): void {
            $score = $this->scoring->score((int) $locked->delay_days);
            $locked->forceFill([
                'liability_status' => VendorComplianceLiabilityStatus::Confirmed,
                'severity' => $score['severity'],
                'demerit_points' => $score['points'],
                'scoring_rule_version' => LateDeliveryScoring::VERSION,
                'liability_reviewed_by' => $actor->id,
                'liability_reviewed_at' => now(),
            ])->save();

            $locked->events()->create([
                'event' => 'confirmed',
                'actor_id' => $actor->id,
                'from_liability_status' => VendorComplianceLiabilityStatus::Pending,
                'to_liability_status' => VendorComplianceLiabilityStatus::Confirmed,
                'metadata' => ['delay_days' => (int) $locked->delay_days, 'severity' => $score['severity'], 'points' => $score['points'], 'scoring_rule_version' => LateDeliveryScoring::VERSION],
            ]);
        });
    }

    /** @throws AuthorizationException|ValidationException */
    public function waive(User $actor, VendorComplianceIncident $incident, string $reason): VendorComplianceIncident
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['waiver_reason' => 'Alasan pengecualian wajib diisi minimal 5 karakter.']);
        }

        return $this->decide($actor, $incident, function (VendorComplianceIncident $locked) use ($actor, $reason): void {
            $locked->forceFill([
                'liability_status' => VendorComplianceLiabilityStatus::Waived,
                'demerit_points' => 0,
                'waiver_reason' => $reason,
                'liability_reviewed_by' => $actor->id,
                'liability_reviewed_at' => now(),
                'status' => 'resolved',
                'handled_by' => $actor->id,
                'resolved_at' => $locked->resolved_at ?? now(),
            ])->save();

            $locked->events()->create([
                'event' => 'waived',
                'actor_id' => $actor->id,
                'from_liability_status' => VendorComplianceLiabilityStatus::Pending,
                'to_liability_status' => VendorComplianceLiabilityStatus::Waived,
                'reason' => $reason,
                'metadata' => ['delay_days' => (int) $locked->delay_days],
            ]);
        });
    }

    /** @param  callable(VendorComplianceIncident): void  $apply */
    private function decide(User $actor, VendorComplianceIncident $incident, callable $apply): VendorComplianceIncident
    {
        return DB::transaction(function () use ($actor, $incident, $apply): VendorComplianceIncident {
            $locked = VendorComplianceIncident::query()->whereKey($incident->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->isLateDelivery()) {
                throw ValidationException::withMessages(['liability_status' => 'Verifikasi liability hanya untuk insiden Late Delivery.']);
            }
            if ($locked->liability_status !== VendorComplianceLiabilityStatus::Pending) {
                throw ValidationException::withMessages(['liability_status' => 'Insiden ini sudah diputuskan sebagai '.$locked->liability_status->getLabel().'.']);
            }
            Gate::forUser($actor)->authorize('reviewLiability', $locked);

            $apply($locked);

            return $locked;
        });
    }
}
