<?php

namespace Database\Factories;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheduled_date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'requestor_notes' => fake()->optional()->sentence(),
            'attachments' => null,
            'status' => ServiceRequestStatus::Submitted,
            'scheduled_by' => User::factory(),
        ];
    }
}
