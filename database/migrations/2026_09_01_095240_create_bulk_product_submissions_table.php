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
        Schema::create('bulk_product_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('operation', 10);
            $table->string('product_code', 50)->nullable();
            $table->string('product_name', 100);
            $table->json('target_comcodes');
            $table->json('remote_product_ids')->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_product_submissions');
    }
};
