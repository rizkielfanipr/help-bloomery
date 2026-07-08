<?php

namespace Database\Seeders;

use App\Models\Trip;
use App\Models\TripRoute;
use Illuminate\Database\Seeder;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        TripRoute::factory(5)->create();

        Trip::factory(30)->create();
        Trip::factory(3)->inProgress()->create();
        Trip::factory(5)->pending()->create();
    }
}
