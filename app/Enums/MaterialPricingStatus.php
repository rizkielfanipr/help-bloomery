<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Pricelist state of a material and of each Pricing Request (§11.4). `priced` is only written
 * after verification; a saved draft price never counts as priced.
 */
enum MaterialPricingStatus: string implements HasColor, HasLabel
{
    case NotReady = 'not_ready';
    case ReadyForPricing = 'ready_for_pricing';
    case DraftPricing = 'draft_pricing';
    case Submitting = 'submitting';
    case VerificationRequired = 'verification_required';
    case Priced = 'priced';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotReady => 'Not Ready',
            self::ReadyForPricing => 'Ready for Pricing',
            self::DraftPricing => 'Draft Pricing',
            self::Submitting => 'Submitting',
            self::VerificationRequired => 'Verification Required',
            self::Priced => 'Priced',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotReady, self::Cancelled => 'gray',
            self::ReadyForPricing, self::DraftPricing, self::VerificationRequired => 'warning',
            self::Submitting => 'info',
            self::Priced => 'success',
            self::Failed => 'danger',
        };
    }

    /** Pricing Request still occupies the material's single active pricing slot. */
    public function isActive(): bool
    {
        return in_array($this, [self::ReadyForPricing, self::DraftPricing, self::Submitting, self::VerificationRequired, self::Failed], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->getLabel()])->all();
    }
}
