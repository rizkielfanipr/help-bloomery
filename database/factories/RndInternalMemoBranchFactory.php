<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndInternalMemoBranch>
 */
class RndInternalMemoBranchFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        // BranchEsbCode has no HasFactory trait (house convention: created via the esbCodes()
        // relation, e.g. `$branch->esbCodes()->create([...])`), so its row is built the same way.
        $branch = Branch::factory()->create();
        $mapping = $branch->esbCodes()->create([
            'esb_comcode' => RndInternalMemo::COMPANY_CODE,
            'esb_branch_code' => strtoupper($this->faker->lexify('???')),
        ]);

        return [
            'rnd_internal_memo_id' => RndInternalMemo::factory(),
            'branch_id' => $branch->id,
            'branch_esb_code_id' => $mapping->id,
            'branch_name_snapshot' => $branch->name,
            'company_code_snapshot' => $mapping->esb_comcode,
            'branch_code_snapshot' => $mapping->esb_branch_code,
            'esb_branch_id_snapshot' => $this->faker->numberBetween(1, 100),
            'catalog_sync_status' => 'pending',
        ];
    }

    public function synced(): static
    {
        return $this->state(fn (): array => [
            'catalog_sync_status' => 'synced',
            'catalog_synced_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'catalog_sync_status' => 'failed',
            'catalog_sync_error' => 'Gagal menghubungi ESB.',
        ]);
    }
}
