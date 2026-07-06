<?php

namespace Database\Factories;

use App\Models\CasualPosition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function casualStaff(): static
    {
        return $this->state(function (): array {
            $phone = '08'.fake()->numerify('##########');
            $phoneDigits = preg_replace('/\D/', '', $phone);

            return [
                'phone' => $phone,
                'username' => 'casual_'.$phoneDigits,
                'email' => $phone.'@casual.app',
                'bank_name' => fake()->randomElement(['BCA', 'BRI', 'BNI', 'Mandiri', 'BSI', 'CIMB']),
                'bank_account_number' => fake()->numerify('################'),
                'is_active' => true,
                'casual_position_id' => CasualPosition::inRandomOrder()->value('id'),
            ];
        })->afterCreating(function (User $user): void {
            $user->assignRole('casual_staff');
        });
    }
}
