<?php

namespace Database\Factories;

use App\Models\CustomerComplaint;
use App\Models\CustomerComplaintActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerComplaintActivity>
 */
class CustomerComplaintActivityFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'customer_complaint_id' => CustomerComplaint::factory(),
            'activity_type' => CustomerComplaintActivity::TYPE_CREATED,
            'created_by' => User::factory(),
        ];
    }
}
