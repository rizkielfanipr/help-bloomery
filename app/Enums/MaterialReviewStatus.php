<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * IT review gate before a Master Bahan/WIP may be linked or pushed to ESB
 * (docs/products/rnd/material-sourcing-lifecycle/prd.md §11.1). Used for the material's current review state
 * and for each submission version row.
 */
enum MaterialReviewStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case WaitingItReview = 'waiting_it_review';
    case UnderItReview = 'under_it_review';
    case RevisionRequired = 'revision_required';
    case ApprovedForEsb = 'approved_for_esb';
    case ReviewCancelled = 'review_cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::WaitingItReview => 'Waiting IT Review',
            self::UnderItReview => 'Under IT Review',
            self::RevisionRequired => 'Revision Required',
            self::ApprovedForEsb => 'Approved for ESB',
            self::ReviewCancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft, self::ReviewCancelled => 'gray',
            self::WaitingItReview, self::RevisionRequired => 'warning',
            self::UnderItReview => 'info',
            self::ApprovedForEsb => 'success',
        };
    }

    /** R&D may only edit the material in these states (§12.5). */
    public function isEditableByRnd(): bool
    {
        return in_array($this, [self::Draft, self::RevisionRequired, self::ReviewCancelled], true);
    }

    public function isActiveReview(): bool
    {
        return in_array($this, [self::WaitingItReview, self::UnderItReview], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->getLabel()])->all();
    }
}
