<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_regions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rnd_product_regional_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_region_id')->constrained()->restrictOnDelete();
            $table->decimal('offline_price', 15, 2)->default(0);
            $table->decimal('online_price', 15, 2)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['rnd_project_product_id', 'sales_region_id', 'effective_from'],
                'rnd_product_region_effective_unique',
            );
            $table->index(['sales_region_id', 'status', 'effective_from'], 'rnd_region_price_active_index');
        });

        $now = now();
        $regions = [
            ['name' => 'Jakarta', 'code' => 'JKT', 'sort_order' => 1],
            ['name' => 'Jogja - Jawa Tengah', 'code' => 'JOG-JTG', 'sort_order' => 2],
            ['name' => 'Surabaya', 'code' => 'SBY', 'sort_order' => 3],
        ];
        foreach ($regions as $region) {
            DB::table('sales_regions')->insert($region + [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $regionIds = DB::table('sales_regions')->pluck('id');
        DB::table('rnd_project_products')->orderBy('id')->get()->each(function ($product) use ($regionIds, $now): void {
            foreach ($regionIds as $regionId) {
                DB::table('rnd_product_regional_prices')->insert([
                    'rnd_project_product_id' => $product->id,
                    'sales_region_id' => $regionId,
                    'offline_price' => $product->offline_price,
                    'online_price' => $product->online_price,
                    'effective_from' => substr((string) $product->created_at, 0, 10) ?: $now->toDateString(),
                    'status' => 'active',
                    'created_by' => $product->created_by,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_product_regional_prices');
        Schema::dropIfExists('sales_regions');
    }
};
