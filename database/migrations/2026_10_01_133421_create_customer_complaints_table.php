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
        Schema::create('customer_complaints', function (Blueprint $table): void {
            $table->id();
            $table->string('complaint_number')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->string('source', 30);
            $table->string('category', 30);
            $table->string('order_reference', 100)->nullable();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_contact', 100)->nullable();
            $table->text('description');
            $table->json('attachment_paths')->nullable();
            $table->string('status', 20)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_at');
            $table->index('order_reference');
            $table->index('status');
            $table->index('assigned_to');
            $table->index('submitted_by');
            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_complaints');
    }
};
