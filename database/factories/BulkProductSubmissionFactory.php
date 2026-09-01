<?php

namespace Database\Factories;

use App\Models\BulkProductSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BulkProductSubmission>
 */
class BulkProductSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operation' => 'create',
            'product_code' => fake()->unique()->bothify('PRD-####'),
            'product_name' => fake()->words(3, true),
            'target_comcodes' => ['BLO7'],
            'payload' => [
                'categoryID' => 1,
                'subCategoryID' => 1,
                'productCode' => fake()->unique()->bothify('PRD-####'),
                'productName' => fake()->words(3, true),
                'productDetails' => [['uomID' => 2, 'qty' => 1, 'isStock' => true, 'isBase' => true]],
            ],
            'status' => 'pending',
            'created_by' => User::factory(),
        ];
    }
}
