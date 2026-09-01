<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_product_submission_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bulk_product_submission_id')->constrained()->cascadeOnDelete();
            $table->string('comcode', 10);
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('remote_product_id')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['bulk_product_submission_id', 'comcode'], 'bulk_product_submission_comcode_unique');
            $table->index(['comcode', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_product_submission_items');
    }
};
