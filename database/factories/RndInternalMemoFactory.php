<?php

namespace Database\Factories;

use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndInternalMemo>
 */
class RndInternalMemoFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        // A wide, non-unique range: tests that create several memos in the same run and need
        // distinct periods pass an explicit `period_month` override rather than relying on luck.
        $periodMonth = $this->faker->dateTimeBetween('-3 years', '+3 years')->format('Y-m-01');

        return [
            'company_code' => RndInternalMemo::COMPANY_CODE,
            'memo_number' => 'MEMO-'.$this->faker->unique()->numerify('######'),
            'title' => 'Memo Internal R&D '.$this->faker->monthName(),
            'period_month' => $periodMonth,
            'memo_date' => now()->toDateString(),
            'recipient' => 'Tim Operasional',
            'sender' => 'Tim R&D',
            'subject' => 'Rilis Menu dan Kebutuhan Bahan',
            'status' => RndInternalMemoStatus::Draft,
            'revision' => 1,
            'created_by' => User::factory(),
        ];
    }

    public function finalized(): static
    {
        return $this->state(fn (): array => [
            'status' => RndInternalMemoStatus::Finalized,
            'finalized_at' => now(),
            'snapshot_hash' => hash('sha256', (string) microtime(true)),
        ]);
    }
}
