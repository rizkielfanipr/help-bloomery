<?php

namespace Database\Factories;

use App\Models\StoreSop;
use App\Models\StoreSopCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreSop>
 */
class StoreSopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SOP-'.fake()->unique()->numerify('####'),
            'title' => fake()->sentence(4),
            'store_sop_category_id' => StoreSopCategory::factory(),
            'summary' => fake()->sentence(),
            'file_path' => 'operational/store-sops/test.pdf',
            'original_name' => 'test.pdf',
            'effective_date' => today(),
            'expires_at' => today()->addYear(),
            'status' => 'draft',
        ];
    }
}
