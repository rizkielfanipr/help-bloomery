<?php

namespace Database\Factories;

use App\Models\StoreSalesOrder;
use App\Models\StoreSalesOrderActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreSalesOrderActivity>
 */
class StoreSalesOrderActivityFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'store_sales_order_id' => StoreSalesOrder::factory(),
            'activity_type' => StoreSalesOrderActivity::TYPE_CREATED,
            'created_by' => User::factory(),
        ];
    }
}
