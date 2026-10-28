<?php

namespace Database\Factories;

use App\Models\EsbProductionOrder;
use App\Models\EsbProductionOrderDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EsbProductionOrderDetail>
 */
class EsbProductionOrderDetailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'esb_production_order_id' => EsbProductionOrder::factory(),
            'external_detail_id' => (string) fake()->unique()->numberBetween(1000, 999999),
            'bom_id' => '7301',
            'bom_name' => 'Adonan Roti Contoh',
            'bom_type_name' => 'Assembly',
            'product_detail_id' => '15003',
            'product_code' => 'WIP-0001',
            'product_name' => 'Adonan Roti Contoh',
            'quantity' => '12.5000',
            'uom_name' => 'KG',
            'notes' => null,
            'is_finished' => false,
            'materials' => [['product_code' => 'RAW-0001', 'product_name' => 'Tepung Contoh', 'quantity' => '7.25', 'uom_name' => 'KG']],
            'sequence' => 1,
        ];
    }
}
