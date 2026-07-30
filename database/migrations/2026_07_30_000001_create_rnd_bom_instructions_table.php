<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_bom_instructions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_id')->constrained('rnd_projects')->cascadeOnDelete();
            $table->foreignId('rnd_project_product_id')->constrained('rnd_project_products')->cascadeOnDelete();
            $table->unsignedBigInteger('esb_bom_id');
            $table->longText('content_html')->nullable();
            $table->json('image_paths')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['rnd_project_id', 'rnd_project_product_id', 'esb_bom_id'],
                'rnd_bom_instruction_scope_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_bom_instructions');
    }
};
