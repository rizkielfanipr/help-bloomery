<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_product_esb_material_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_product_esb_material_id');
            $table->unsignedBigInteger('uom_id');
            $table->string('uom_name', 50);
            $table->string('sku', 50)->unique();
            $table->decimal('conversion_factor', 20, 4)->default(1);
            $table->decimal('base_price', 20, 4)->default(0);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_stock')->default(false);
            $table->boolean('is_purchase')->default(false);
            $table->boolean('is_transfer')->default(false);
            $table->boolean('is_sales')->default(false);
            $table->boolean('flag_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['rnd_product_esb_material_id', 'uom_id'],
                'rnd_esb_material_unit_unique',
            );
            $table->foreign('rnd_product_esb_material_id', 'rnd_esb_material_unit_material_fk')
                ->references('id')
                ->on('rnd_product_esb_materials')
                ->cascadeOnDelete();
        });

        $now = now();
        DB::table('rnd_product_esb_materials')
            ->orderBy('id')
            ->each(function (object $material) use ($now): void {
                DB::table('rnd_product_esb_material_units')->insert([
                    'rnd_product_esb_material_id' => $material->id,
                    'uom_id' => $material->uom_id,
                    'uom_name' => $material->uom_name,
                    'sku' => $material->sku,
                    'conversion_factor' => 1,
                    'base_price' => $material->base_price,
                    'is_base' => true,
                    'is_stock' => true,
                    'is_purchase' => true,
                    'is_transfer' => true,
                    'is_sales' => false,
                    'flag_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_product_esb_material_units');
    }
};
