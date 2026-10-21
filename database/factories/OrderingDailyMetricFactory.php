<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\OrderingDailyMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderingDailyMetric>
 */
class OrderingDailyMetricFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'company_code' => 'BLSS',
            'esb_branch_code' => 'BPL',
            'metric_date' => now()->subDay()->toDateString(),
            'product_code' => 'BW-'.fake()->unique()->numerify('####'),
            'product_name' => fake()->words(2, true),
            'category_name' => 'Barang WIP',
            'uom' => 'PCS',
            'sales_driven_usage' => 10,
            'input_version' => 'v1',
        ];
    }

    /** Same product code and UOM in another company of the same branch (never merged). */
    public function otherCompany(string $companyCode = 'BLSK', string $esbBranchCode = 'BPK'): static
    {
        return $this->state(fn (): array => ['company_code' => $companyCode, 'esb_branch_code' => $esbBranchCode]);
    }

    public function uom(string $uom): static
    {
        return $this->state(fn (): array => ['uom' => $uom]);
    }
}
