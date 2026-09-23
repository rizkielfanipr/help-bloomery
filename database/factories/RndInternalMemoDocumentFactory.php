<?php

namespace Database\Factories;

use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RndInternalMemoDocument>
 */
class RndInternalMemoDocumentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'rnd_internal_memo_id' => RndInternalMemo::factory(),
            'revision' => 1,
            'disk' => 'local',
            'file_path' => 'rnd/internal-memos/'.$this->faker->uuid().'.pdf',
            'file_size' => $this->faker->numberBetween(20_000, 500_000),
            'checksum' => hash('sha256', $this->faker->uuid()),
            'generated_at' => now(),
        ];
    }
}
