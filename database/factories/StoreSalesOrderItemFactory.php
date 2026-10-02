<?php

namespace Database\Factories;

use App\Enums\StoreSalesOrderProductType;
use App\Models\StoreSalesOrder;
use App\Models\StoreSalesOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreSalesOrderItem>
 */
class StoreSalesOrderItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'store_sales_order_id' => StoreSalesOrder::factory(),
            'product_type' => StoreSalesOrderProductType::SnackBox,
            'quantity' => $this->faker->numberBetween(1, 20),
            'sort_order' => 1,
        ];
    }

    public function custom(): static
    {
        return $this->state(fn (): array => [
            'product_type' => StoreSalesOrderProductType::Custom,
            'custom_detail' => $this->faker->sentence(),
        ]);
    }
}
