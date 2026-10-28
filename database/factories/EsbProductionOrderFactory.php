<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchEsbCode;
use App\Models\EsbProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EsbProductionOrder>
 */
class EsbProductionOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_esb_code_id' => null,
            'branch_id' => Branch::factory()->kitchen(),
            'company_code' => 'BLSS',
            'esb_branch_id' => 501,
            'esb_branch_name' => 'Kitchen Contoh',
            'production_order_num' => 'PO-'.fake()->unique()->numerify('######'),
            'production_order_date' => now()->toDateString(),
            'bom_type_id' => '1',
            'bom_type_name' => 'Assembly',
            'status_id' => '2',
            'status_name' => 'Authorized',
            'additional_info' => null,
            'source_fetched_at' => now(),
            'source_last_seen_at' => now(),
            'details_synced_at' => now(),
            'payload_hash' => hash('sha256', fake()->uuid()),
        ];
    }

    /**
     * Snapshot owned by an existing mapping: Branch and ESB context come from the mapping.
     */
    public function forMapping(BranchEsbCode $mapping): static
    {
        return $this->state(fn (): array => [
            'branch_esb_code_id' => $mapping->id,
            'branch_id' => $mapping->branch_id,
            'company_code' => $mapping->esb_comcode,
            'esb_branch_id' => $mapping->esb_branch_id,
        ]);
    }

    public function withoutDetails(): static
    {
        return $this->state(fn (): array => ['details_synced_at' => null]);
    }
}
