<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_project_boms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('esb_bom_id')->unique();
            $table->string('bom_code')->nullable();
            $table->string('bom_name');
            $table->string('product_name')->nullable();
            $table->string('uom_name', 100)->nullable();
            $table->string('bom_type_name', 100)->default('Assembly');
            $table->boolean('is_active')->default(true);
            $table->string('sync_status', 30)->default('synced');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['rnd_project_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_project_boms');
    }
};
