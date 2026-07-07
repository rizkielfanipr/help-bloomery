<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = User::firstOrCreate(
            ['email' => 'admin@bloomery.org'],
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles(['SUPERADMIN']);

        $hr = User::firstOrCreate(
            ['email' => 'hr@bloomery.test'],
            [
                'name' => 'HRD Staff',
                'username' => 'hrd.staff',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $hr->syncRoles(['HRD_STAFF']);

        $casual = User::firstOrCreate(
            ['email' => 'casual@bloomery.test'],
            [
                'name' => 'Casual Worker',
                'username' => 'casual.worker',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $casual->syncRoles(['CASUAL_STAFF']);

        $store = User::firstOrCreate(
            ['email' => 'store@bloomery.test'],
            [
                'name' => 'Store Staff',
                'username' => 'store.staff',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $store->syncRoles(['STORE_STAFF']);

        $driver = User::firstOrCreate(
            ['email' => 'driver@bloomery.test'],
            [
                'name' => 'Driver',
                'username' => 'driver',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $driver->syncRoles(['DRIVER']);

        $tech = User::firstOrCreate(
            ['email' => 'tech@bloomery.test'],
            [
                'name' => 'Technician',
                'username' => 'technician',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $tech->syncRoles(['TECHNICIAN']);
    }
}
