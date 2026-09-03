<?php

namespace Database\Seeders;

use App\Models\ComplimentType;
use Illuminate\Database\Seeder;

class ComplimentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Compliment Owner', 'Compliment Training'] as $sortOrder => $name) {
            ComplimentType::updateOrCreate(
                ['name' => $name],
                ['sort_order' => $sortOrder + 1, 'is_active' => true],
            );
        }
    }
}
