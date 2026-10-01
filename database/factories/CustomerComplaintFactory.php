<?php

namespace Database\Factories;

use App\Enums\CustomerComplaintCategory;
use App\Enums\CustomerComplaintSource;
use App\Enums\CustomerComplaintStatus;
use App\Models\Branch;
use App\Models\CustomerComplaint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerComplaint>
 */
class CustomerComplaintFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'complaint_number' => 'CMP-'.now()->format('Ymd').'-'.$this->faker->unique()->numerify('####'),
            'branch_id' => Branch::factory(),
            'occurred_at' => now()->subHours($this->faker->numberBetween(1, 48)),
            'source' => $this->faker->randomElement(CustomerComplaintSource::cases()),
            'category' => $this->faker->randomElement(CustomerComplaintCategory::cases()),
            'order_reference' => $this->faker->optional()->numerify('TRX-######'),
            'customer_name' => $this->faker->optional()->name(),
            'customer_contact' => $this->faker->optional()->phoneNumber(),
            'description' => $this->faker->sentence(12),
            'status' => CustomerComplaintStatus::New,
            'submitted_by' => User::factory(),
        ];
    }

    public function inReview(): static
    {
        return $this->state(fn (): array => ['status' => CustomerComplaintStatus::InReview]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => CustomerComplaintStatus::Resolved,
            'resolution' => $this->faker->sentence(10),
            'resolved_at' => now(),
            'resolved_by' => User::factory(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => CustomerComplaintStatus::Closed,
            'resolution' => $this->faker->sentence(10),
            'resolved_at' => now()->subHour(),
            'resolved_by' => User::factory(),
            'closed_at' => now(),
            'closed_by' => User::factory(),
        ]);
    }
}
