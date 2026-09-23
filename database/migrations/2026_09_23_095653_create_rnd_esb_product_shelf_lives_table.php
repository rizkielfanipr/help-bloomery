<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_esb_product_shelf_lives', function (Blueprint $table): void {
            $table->id();
            $table->string('company_code', 10)->default('BLSS');
            $table->unsignedBigInteger('esb_product_id')->nullable();
            $table->unsignedBigInteger('esb_product_detail_id')->nullable();
            $table->unsignedBigInteger('esb_menu_id')->nullable();
            $table->string('product_code')->nullable();
            $table->string('product_name');
            $table->decimal('shelf_life_value', 10, 2);
            $table->string('shelf_life_unit', 20);
            $table->string('storage_condition');
            $table->text('notes')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_code', 'esb_menu_id']);
            $table->index(['company_code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_esb_product_shelf_lives');
    }
};
