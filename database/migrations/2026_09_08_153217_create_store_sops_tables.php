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
        Schema::create('store_sops', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('title');
            $table->string('version', 30)->default('1.0');
            $table->string('category', 100)->nullable();
            $table->text('summary')->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->date('effective_date');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('store_sop_branch', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_sop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['store_sop_id', 'branch_id'], 'store_sop_branch_unique');
        });

        Schema::create('store_sop_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_sop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['store_sop_id', 'branch_id', 'user_id'], 'store_sop_assignment_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_sop_assignments');
        Schema::dropIfExists('store_sop_branch');
        Schema::dropIfExists('store_sops');
    }
};
