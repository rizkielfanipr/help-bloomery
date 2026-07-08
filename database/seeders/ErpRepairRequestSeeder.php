<?php

namespace Database\Seeders;

use App\Models\ErpRepairRequest;
use Illuminate\Database\Seeder;

class ErpRepairRequestSeeder extends Seeder
{
    public function run(): void
    {
        ErpRepairRequest::factory(25)->create();
    }
}
