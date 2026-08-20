<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class AllUsersSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // --- SUPERADMIN ---
        User::updateOrCreate(
            ['email' => 'admin@bloomery.org'],
            [
                'name' => 'Super Admin',
                'username' => 'BLOADMIN',
                'password' => Hash::make('password'),
                'branch_id' => 1,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['SUPERADMIN']);

        // --- HRD STAFF ---
        User::updateOrCreate(
            ['email' => 'hr@bloomery.test'],
            [
                'name' => 'HRD Staff',
                'username' => 'BLOHRD1',
                'password' => Hash::make('password'),
                'branch_id' => 1,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['HRD_STAFF']);

        User::updateOrCreate(
            ['email' => 'hr2@bloomery.test'],
            [
                'name' => 'HRD Staff 2',
                'username' => 'BLOHRD2',
                'password' => Hash::make('password'),
                'branch_id' => 3,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['HRD_STAFF']);

        // --- STORE STAFF ---
        User::updateOrCreate(
            ['email' => 'store@bloomery.test'],
            [
                'name' => 'Store Staff Utama',
                'username' => 'BLOSTORE1',
                'password' => Hash::make('password'),
                'branch_id' => 1,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['STORE_STAFF']);

        User::updateOrCreate(
            ['email' => 'store2@bloomery.test'],
            [
                'name' => 'Store Staff Gudang',
                'username' => 'BLOSTORE2',
                'password' => Hash::make('password'),
                'branch_id' => 2,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['STORE_STAFF']);

        User::updateOrCreate(
            ['email' => 'store3@bloomery.test'],
            [
                'name' => 'Store Staff Cabang',
                'username' => 'BLOSTORE3',
                'password' => Hash::make('password'),
                'branch_id' => 3,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['STORE_STAFF']);

        // --- DRIVER ---
        User::updateOrCreate(
            ['email' => 'driver@bloomery.test'],
            [
                'name' => 'Driver Utama',
                'username' => 'BLODRIVER1',
                'password' => Hash::make('password'),
                'branch_id' => 1,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['DRIVER']);

        User::updateOrCreate(
            ['email' => 'driver2@bloomery.test'],
            [
                'name' => 'Driver 2',
                'username' => 'BLODRIVER2',
                'password' => Hash::make('password'),
                'branch_id' => 2,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['DRIVER']);

        // --- TECHNICIAN ---
        User::updateOrCreate(
            ['email' => 'tech@bloomery.test'],
            [
                'name' => 'Teknisi 1',
                'username' => 'BLOTECH1',
                'password' => Hash::make('password'),
                'branch_id' => 1,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['TECHNICIAN']);

        User::updateOrCreate(
            ['email' => 'tech2@bloomery.test'],
            [
                'name' => 'Teknisi 2',
                'username' => 'BLOTECH2',
                'password' => Hash::make('password'),
                'branch_id' => 2,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['TECHNICIAN']);

        // --- SUPERVISOR STORE ---
        User::updateOrCreate(
            ['email' => 'store-leader@bloomery.test'],
            [
                'name' => 'Store Leader Demo',
                'username' => 'BLOSPV1',
                'password' => Hash::make('password'),
                'branch_id' => 1,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['SUPERVISOR_STORE']);

        // --- QUALITY CONTROL ---
        User::updateOrCreate(
            ['email' => 'qc@bloomery.test'],
            [
                'name' => 'QC Auditor',
                'username' => 'BLOQC1',
                'password' => Hash::make('password'),
                'branch_id' => 1,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['QUALITY_CONTROL']);

        User::updateOrCreate(
            ['email' => 'qc-supervisor@bloomery.test'],
            [
                'name' => 'QC Supervisor',
                'username' => 'BLOQCSPV',
                'password' => Hash::make('password'),
                'branch_id' => 1,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['QUALITY_CONTROL_SUPERVISOR']);

        // --- CASUAL STAFF (demo — username bebas, tidak pakai prefix BLO) ---
        User::updateOrCreate(
            ['email' => 'casual@bloomery.test'],
            [
                'name' => 'Casual Staff Demo',
                'username' => 'casualdemo',
                'password' => Hash::make('password'),
                'branch_id' => 1,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        )->syncRoles(['CASUAL_STAFF']);

        // Assign CASUAL_STAFF + username to existing self-registered @casual.app users
        User::where('email', 'like', '%@casual.app')
            ->get()
            ->each(function (User $user) {
                if ($user->roles->isEmpty()) {
                    $user->syncRoles(['CASUAL_STAFF']);
                }

                if (! filled($user->username)) {
                    $base = strtolower(str_replace(' ', '', $user->name));
                    $username = $base;
                    $counter = 1;
                    while (User::where('username', $username)->where('id', '!=', $user->id)->exists()) {
                        $username = $base.$counter++;
                    }
                    $user->update(['username' => $username]);
                }
            });

        $this->command->info('All users seeded and roles assigned.');
    }
}
