<?php

namespace Database\Factories;

use App\Models\HelpdeskRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HelpdeskRequest>
 */
class HelpdeskRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => 'draft',
            'data' => [],
        ];
    }
}
