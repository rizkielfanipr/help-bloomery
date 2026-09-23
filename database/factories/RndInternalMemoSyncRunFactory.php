<?php

namespace Database\Factories;

use App\Enums\RndInternalMemoSyncRunStatus;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndInternalMemoSyncRun>
 */
class RndInternalMemoSyncRunFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'rnd_internal_memo_id' => RndInternalMemo::factory(),
            'company_code' => RndInternalMemo::COMPANY_CODE,
            'status' => RndInternalMemoSyncRunStatus::Pending,
            'started_at' => now(),
        ];
    }
}
