<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Sourcing/sample state of a material (docs/products/rnd/material-sourcing-lifecycle/prd.md §11.3).
 *
 * The first five cases belong to the legacy single-step supplier review (R&D → Finance) used by
 * records created before the Master Bahan workflow and by standalone sourcing; they stay readable
 * and actionable for those rows. Materials in the new workflow only use the PRD cases below them.
 */
enum MaterialSourcingStatus: string implements HasColor, HasLabel
{
    case NotStarted = 'not_started';
    case PendingRndReview = 'pending_rnd_review';
    case PendingFinanceReview = 'pending_finance_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    case NotRequired = 'not_required';
    case WaitingPurchasing = 'waiting_purchasing';
    case SourcingInProgress = 'sourcing_in_progress';
    case SamplePrepared = 'sample_prepared';
    case SampleHandedOver = 'sample_handed_over';
    case SampleReceived = 'sample_received';
    case UnderRndReview = 'under_rnd_review';
    case ResampleRequested = 'resample_requested';
    case ApprovedByRnd = 'approved_by_rnd';
    case RejectedByRnd = 'rejected_by_rnd';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::PendingRndReview => 'R&D Review',
            self::PendingFinanceReview => 'Finance Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::NotRequired => 'Not Required',
            self::WaitingPurchasing => 'Waiting for Purchasing',
            self::SourcingInProgress => 'Sourcing in Progress',
            self::SamplePrepared => 'Sample Prepared',
            self::SampleHandedOver => 'Sample Handed Over',
            self::SampleReceived => 'Sample Received',
            self::UnderRndReview => 'Under R&D Review',
            self::ResampleRequested => 'Resample Requested',
            self::ApprovedByRnd => 'Approved by R&D',
            self::RejectedByRnd => 'Rejected by R&D',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotStarted, self::NotRequired => 'gray',
            self::PendingRndReview, self::PendingFinanceReview, self::WaitingPurchasing, self::ResampleRequested => 'warning',
            self::SourcingInProgress, self::SamplePrepared, self::SampleHandedOver, self::SampleReceived, self::UnderRndReview => 'info',
            self::Approved, self::ApprovedByRnd => 'success',
            self::Rejected, self::RejectedByRnd => 'danger',
        };
    }

    public function isLegacy(): bool
    {
        return in_array($this, [self::NotStarted, self::PendingRndReview, self::PendingFinanceReview, self::Approved, self::Rejected], true);
    }
}
