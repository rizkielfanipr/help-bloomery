<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_project_marketing_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rnd_project_product_id', 'type'], 'rnd_product_material_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_project_marketing_materials');
    }
};
