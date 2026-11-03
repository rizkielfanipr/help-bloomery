<?php

namespace Database\Factories;

use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketingDesignItem>
 */
class MarketingDesignItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'marketing_design_bundle_id' => MarketingDesignBundle::factory(),
            'type' => 'social_media',
            'title' => fake()->words(2, true),
        ];
    }

    public function physical(): static
    {
        return $this->state(fn (): array => [
            'type' => 'packaging_design',
            'dimensions' => '10 x 10 x 5 cm',
            'material_specification' => 'Art carton 310 gsm',
        ]);
    }
}
