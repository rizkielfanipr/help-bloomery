<?php

namespace Database\Seeders;

use App\Models\PurchasingRequest;
use App\Models\PurchasingRequestItem;
use Illuminate\Database\Seeder;

class PurchasingRequestSeeder extends Seeder
{
    public function run(): void
    {
        PurchasingRequest::factory(20)->create()->each(function (PurchasingRequest $request) {
            PurchasingRequestItem::factory(fake()->numberBetween(2, 6))->create([
                'purchasing_request_id' => $request->id,
            ]);
        });
    }
}
