<?php

namespace Database\Factories;

use App\Enums\ServiceRequestStatus;
use App\Models\Branch;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    public function definition(): array
    {
        $status = fake()->randomElement(ServiceRequestStatus::cases());

        return [
            'technician_id' => User::role('TECHNICIAN')->inRandomOrder()->value('id'),
            'scheduled_by' => User::inRandomOrder()->value('id'),
            'branch_id' => Branch::inRandomOrder()->value('id'),
            'scheduled_date' => fake()->dateTimeBetween('-60 days', '+14 days')->format('Y-m-d'),
            'requestor_notes' => fake()->optional(0.6)->sentence(10),
            'attachments' => null,
            'status' => $status,
        ];
    }
}
