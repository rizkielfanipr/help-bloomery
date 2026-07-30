<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_product_esb_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('category_id');
            $table->string('category_name')->nullable();
            $table->unsignedBigInteger('sub_category_id');
            $table->string('sub_category_name')->nullable();
            $table->unsignedBigInteger('uom_id');
            $table->string('uom_name', 50);
            $table->string('product_code', 50)->unique();
            $table->string('product_name', 100);
            $table->string('sku', 50)->unique();
            $table->decimal('conversion_factor', 20, 4)->default(1);
            $table->decimal('base_price', 20, 4)->default(0);
            $table->string('notes', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('esb_product_id')->nullable();
            $table->boolean('esb_is_temp')->nullable();
            $table->json('last_payload')->nullable();
            $table->json('last_response')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rnd_project_product_id', 'status'], 'rnd_esb_material_product_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_product_esb_materials');
    }
};
