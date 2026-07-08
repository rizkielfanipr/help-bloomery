<?php

namespace Database\Seeders;

use App\Models\PurchaseRequest;
use Illuminate\Database\Seeder;

class PurchaseRequestSeeder extends Seeder
{
    public function run(): void
    {
        PurchaseRequest::factory(40)->create();
    }
}
