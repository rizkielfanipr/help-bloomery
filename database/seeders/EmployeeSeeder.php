<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /** @var array<int, array{code: string, name: string, position: string, is_active: bool}> */
    private array $data = [
        ['code' => 'EMP-001', 'name' => 'Dewi Anggraini', 'position' => 'Kasir', 'is_active' => true],
        ['code' => 'EMP-002', 'name' => 'Budi Santoso', 'position' => 'Barista', 'is_active' => true],
        ['code' => 'EMP-003', 'name' => 'Siti Nurhaliza', 'position' => 'Baker', 'is_active' => true],
        ['code' => 'EMP-004', 'name' => 'Andi Prasetyo', 'position' => 'Kitchen Staff', 'is_active' => true],
        ['code' => 'EMP-005', 'name' => 'Rina Wulandari', 'position' => 'Supervisor Toko', 'is_active' => true],
        ['code' => 'EMP-006', 'name' => 'Fajar Ramadhan', 'position' => 'Kasir', 'is_active' => true],
        ['code' => 'EMP-007', 'name' => 'Putri Lestari', 'position' => 'Store Crew', 'is_active' => true],
        ['code' => 'EMP-008', 'name' => 'Agus Setiawan', 'position' => 'Barista', 'is_active' => true],
        ['code' => 'EMP-009', 'name' => 'Maya Kusuma', 'position' => 'Waitress', 'is_active' => true],
        ['code' => 'EMP-010', 'name' => 'Rian Hidayat', 'position' => 'Store Crew', 'is_active' => false],
    ];

    public function run(): void
    {
        $branches = Branch::orderBy('id')->get();

        if ($branches->isEmpty()) {
            $this->command->warn('No branches found, skipping EmployeeSeeder.');

            return;
        }

        foreach ($this->data as $index => $row) {
            $branch = $branches[$index % $branches->count()];

            Employee::updateOrCreate(
                ['employee_code' => $row['code']],
                [
                    'branch_id' => $branch->id,
                    'name' => $row['name'],
                    'position' => $row['position'],
                    'is_active' => $row['is_active'],
                ]
            );
        }

        $this->command->info('Seeded '.count($this->data)." employees across {$branches->count()} branch(es).");
    }
}
