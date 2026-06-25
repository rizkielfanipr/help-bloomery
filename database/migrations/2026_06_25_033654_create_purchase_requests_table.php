<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('division');
            $table->string('item_name');
            $table->unsignedInteger('quantity');
            $table->text('purchase_reason');
            $table->string('purchase_type');          // new | broken
            $table->string('journal_item_number')->nullable();
            $table->string('purchase_request_number')->nullable();
            $table->string('ecommerce_link')->nullable();
            $table->json('attachment_paths')->nullable();
            $table->string('status')->default('submitted');
            $table->text('admin_notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
