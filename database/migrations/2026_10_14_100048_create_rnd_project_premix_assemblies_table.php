<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rnd_project_premix_assemblies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('esb_bom_id');
            $table->string('bom_code')->nullable();
            $table->string('bom_name');
            $table->string('product_name')->nullable();
            $table->string('uom_name', 100)->nullable();
            $table->unsignedBigInteger('bom_type_id');
            $table->string('bom_type_name', 100);
            $table->json('detail_snapshot')->nullable();
            $table->boolean('catalog_is_active')->default(true);
            $table->timestamp('catalog_synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rnd_project_id', 'esb_bom_id'], 'rnd_project_premix_bom_unique');
            $table->index(['rnd_project_id', 'created_at'], 'rnd_project_premix_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_project_premix_assemblies');
    }
};
