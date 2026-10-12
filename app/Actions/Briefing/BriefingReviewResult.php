<?php

namespace App\Actions\Briefing;

use App\Models\BriefingItem;

final readonly class BriefingReviewResult
{
    private function __construct(
        public bool $successful,
        public string $reason,
        public string $message,
        public ?BriefingItem $item = null,
    ) {}

    public static function success(BriefingItem $item, string $message): self
    {
        return new self(true, 'ok', $message, $item);
    }

    public static function denied(string $reason, string $message): self
    {
        return new self(false, $reason, $message);
    }
}
