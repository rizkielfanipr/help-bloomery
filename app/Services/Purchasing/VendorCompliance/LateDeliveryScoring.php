<?php

namespace App\Services\Purchasing\VendorCompliance;

/**
 * Severity and demerit points for a confirmed Late Delivery (PRD §26.1 no. 2). The version is stored on
 * the incident so a future matrix never rewrites confirmed points.
 */
class LateDeliveryScoring
{
    public const VERSION = 'late-delivery-v1';

    /** @return array{severity: string, points: int} */
    public function score(int $delayDays): array
    {
        return match (true) {
            $delayDays > 7 => ['severity' => 'high', 'points' => 20],
            $delayDays >= 3 => ['severity' => 'medium', 'points' => 10],
            $delayDays >= 1 => ['severity' => 'low', 'points' => 5],
            default => ['severity' => 'low', 'points' => 0],
        };
    }
}
