<?php

namespace Database\Seeders;

use App\Models\DesignRequest;
use Illuminate\Database\Seeder;

class DesignRequestSeeder extends Seeder
{
    public function run(): void
    {
        DesignRequest::factory(30)->create();
    }
}
