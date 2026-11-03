<?php

namespace Database\Factories;

use App\Enums\MarketingDesignVersionStatus;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignBundleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketingDesignBundleVersion>
 */
class MarketingDesignBundleVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'marketing_design_bundle_id' => MarketingDesignBundle::factory(),
            'version_number' => 1,
            'status' => MarketingDesignVersionStatus::Draft,
        ];
    }

    public function status(MarketingDesignVersionStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => MarketingDesignVersionStatus::Published, 'published_marker' => 1, 'published_at' => now()]);
    }
}
