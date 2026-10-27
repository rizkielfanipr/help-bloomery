<?php

namespace Database\Factories;

use App\Enums\MaterialPricingStatus;
use App\Models\RndMaterialPricingRequest;
use App\Models\RndProductEsbMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RndMaterialPricingRequest>
 */
class RndMaterialPricingRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'rnd_product_esb_material_id' => RndProductEsbMaterial::factory()->sampleApproved(),
            'esb_product_id' => 8100,
            'product_detail_id' => 9100,
            'uom_id' => 5,
            'uom_name' => 'GR',
            'origin' => RndMaterialPricingRequest::ORIGIN_SOURCING,
            'revision' => 1,
            'eligibility_key' => 'pricing:'.Str::uuid(),
            'active_key' => null,
            'status' => MaterialPricingStatus::ReadyForPricing,
            'idempotency_key' => 'pricing-request:'.Str::uuid(),
            'queued_at' => now(),
        ];
    }
}
