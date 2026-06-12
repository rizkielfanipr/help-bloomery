<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $itDepartment = Department::firstOrCreate(
            ['code' => 'IT'],
            ['name' => 'Information Technology'],
        );

        Department::firstOrCreate(['code' => 'OPS'], ['name' => 'Operasional']);
        Department::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resources']);
        Department::firstOrCreate(['code' => 'LOG'], ['name' => 'Logistik']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@bloomery.org'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'employee_id' => 'EMP-001',
                'department_id' => $itDepartment->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $admin->assignRole('super_admin');

        $hrDepartment = Department::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resources']);

        $hr = User::firstOrCreate(
            ['email' => 'hr@bloomery.test'],
            [
                'name' => 'HR Staff',
                'username' => 'hr.staff',
                'password' => Hash::make('password'),
                'employee_id' => 'EMP-HR01',
                'department_id' => $hrDepartment->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $hr->assignRole('hr_staff');

        $casual = User::firstOrCreate(
            ['email' => 'casual@bloomery.test'],
            [
                'name' => 'Casual Worker',
                'username' => 'casual.worker',
                'password' => Hash::make('password'),
                'employee_id' => 'EMP-CS01',
                'department_id' => $hrDepartment->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $casual->assignRole('casual_staff');
    }
}
