<?php

namespace Database\Factories;

use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BriefingItem>
 */
class BriefingItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'briefing_record_id' => BriefingRecord::factory(),
            'task_key' => 'daily_selfie_pagi',
            'photo_paths' => ['briefing-photos/test.jpg'],
            'notes' => null,
            'is_completed' => true,
            'completed_at' => now(),
            'review_status' => BriefingReviewStatus::SupervisorReview->value,
        ];
    }

    public function forTask(string $taskKey): static
    {
        return $this->state(fn (array $attributes): array => [
            'task_key' => $taskKey,
        ]);
    }

    public function approvedBy(User $reviewer): static
    {
        return $this->state(fn (array $attributes): array => [
            'review_status' => BriefingReviewStatus::Approved->value,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }

    public function rejectedBy(User $reviewer, string $reason = 'Foto tidak jelas.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'review_status' => BriefingReviewStatus::Rejected->value,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * A rejection written by the system (no reviewer) for an item nobody ever filled in —
     * the legacy "synthetic rejection" shape the cleanup classifier treats as safe to remove.
     */
    public function systemRejectedWithoutSubmission(): static
    {
        return $this->state(fn (array $attributes): array => [
            'photo_paths' => null,
            'notes' => null,
            'is_completed' => false,
            'completed_at' => null,
            'review_status' => BriefingReviewStatus::Rejected->value,
            'rejection_reason' => 'Tidak disubmit sampai batas waktu.',
            'reviewed_by' => null,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Pre-rename rows still carry `pending`; the data migration converts them, but deployments can
     * read them before it runs.
     */
    public function legacyPending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'review_status' => BriefingReviewStatus::LegacyPending->value,
        ]);
    }
}
