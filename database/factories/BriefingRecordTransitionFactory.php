<?php

namespace Database\Factories;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingTransitionSource;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BriefingRecordTransition>
 */
class BriefingRecordTransitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'briefing_record_id' => BriefingRecord::factory(),
            'from_status' => BriefingBundleStatus::Draft->value,
            'to_status' => BriefingBundleStatus::SupervisorReview->value,
            'actor_id' => null,
            'reason' => null,
            'source' => BriefingTransitionSource::UserAction->value,
            'metadata' => null,
        ];
    }
}
