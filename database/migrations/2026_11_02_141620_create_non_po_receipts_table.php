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
        Schema::create('non_po_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->nullable()->unique();
            $table->uuid('submission_key')->unique();
            $table->string('status', 30)->default('draft');
            $table->string('company_code', 10)->default('BLSS');
            $table->unsignedBigInteger('esb_branch_id')->nullable();
            $table->string('esb_branch_code')->nullable();
            $table->string('branch_name')->nullable();
            $table->foreignId('local_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('location_name')->nullable();
            $table->date('receipt_date');
            $table->string('supplier_text')->nullable();
            $table->string('supplier_contact')->nullable();
            $table->text('reason')->nullable();
            $table->string('document_type')->default('delivery_note');
            $table->string('document_number')->nullable();
            $table->date('document_date')->nullable();
            $table->boolean('document_match')->default(true);
            $table->text('document_notes')->nullable();
            $table->json('document_photos')->nullable();
            $table->json('goods_photos')->nullable();
            $table->text('additional_info')->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('po_number')->nullable();
            $table->date('po_date')->nullable();
            $table->unsignedBigInteger('po_supplier_id')->nullable();
            $table->string('po_supplier_name')->nullable();
            $table->json('po_snapshot')->nullable();
            $table->foreignId('po_linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('po_linked_at')->nullable();
            $table->foreignId('supplier_mismatch_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('supplier_mismatch_confirmed_at')->nullable();
            $table->foreignId('goods_receipt_id')->nullable()->unique()->constrained('goods_receipts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_code', 'po_number']);
            $table->index(['status', 'submitted_at']);
            $table->index(['company_code', 'esb_branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('non_po_receipts');
    }
};
