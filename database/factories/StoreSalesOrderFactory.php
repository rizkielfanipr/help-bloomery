<?php

namespace Database\Factories;

use App\Enums\StoreSalesOrderStatus;
use App\Models\Branch;
use App\Models\StoreSalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreSalesOrder>
 */
class StoreSalesOrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'company_code_snapshot' => 'BLSS',
            'branch_code_snapshot' => 'BLS',
            'esb_branch_id_snapshot' => $this->faker->numberBetween(1, 50),
            'branch_name_snapshot' => $this->faker->city(),
            'product_sales_number' => 'SL-'.$this->faker->unique()->numerify('#####'),
            'product_sales_date' => now()->subDays(2),
            'required_date' => now()->addDays(3),
            'customer_name_snapshot' => $this->faker->name(),
            'customer_address_snapshot' => $this->faker->address(),
            'product_sales_total' => $this->faker->randomFloat(2, 100000, 5000000),
            'currency_sign' => 'Rp',
            'esb_status_name' => 'Open',
            'esb_snapshot' => ['productSalesNum' => 'raw'],
            'last_verified_at' => now(),
            'operational_status' => StoreSalesOrderStatus::Submitted,
            'submitted_by' => User::factory(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'operational_status' => StoreSalesOrderStatus::Cancelled,
            'cancellation_reason' => $this->faker->sentence(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (): array => ['operational_status' => StoreSalesOrderStatus::Delivered]);
    }
}
