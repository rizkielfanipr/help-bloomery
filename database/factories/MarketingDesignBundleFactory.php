<?php

namespace Database\Factories;

use App\Enums\MarketingDesignBundleCategory;
use App\Enums\MarketingDesignBundleStatus;
use App\Models\Brand;
use App\Models\MarketingDesignBundle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketingDesignBundle>
 */
class MarketingDesignBundleFactory extends Factory
{
    public function definition(): array
    {
        $code = 'BND-'.fake()->unique()->numerify('#####');

        return [
            'brand_id' => Brand::factory(),
            'code' => $code,
            'normalized_code' => MarketingDesignBundle::normalizeCode($code),
            'name' => fake()->words(3, true),
            'category' => MarketingDesignBundleCategory::Regular,
            'status' => MarketingDesignBundleStatus::Active,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => MarketingDesignBundleStatus::Archived, 'archived_at' => now(), 'archive_reason' => 'Tidak digunakan']);
    }
}
