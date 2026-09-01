<?php

namespace Database\Factories;

use App\Models\BulkProductSubmission;
use App\Models\BulkProductSubmissionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BulkProductSubmissionItem>
 */
class BulkProductSubmissionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bulk_product_submission_id' => BulkProductSubmission::factory(),
            'comcode' => 'BLO7',
            'status' => 'pending',
            'attempts' => 0,
        ];
    }
}
