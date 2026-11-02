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
        Schema::create('non_po_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('non_po_receipt_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_detail_id');
            $table->string('product_code')->nullable();
            $table->string('product_name');
            $table->string('uom_name', 100);
            $table->decimal('physical_qty', 20, 4)->default(0);
            $table->decimal('accepted_qty', 20, 4)->default(0);
            $table->decimal('hold_qty', 20, 4)->default(0);
            $table->decimal('rejected_qty', 20, 4)->default(0);
            $table->decimal('invoice_price', 20, 4)->nullable();
            $table->string('disposition')->nullable();
            $table->json('qc_data')->nullable();
            $table->json('evidence_photos')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('qc_inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qc_inspected_at')->nullable();
            $table->unsignedBigInteger('purchase_detail_id')->nullable();
            $table->unsignedBigInteger('po_uom_id')->nullable();
            $table->string('po_uom_name')->nullable();
            $table->decimal('po_ordered_qty', 20, 4)->nullable();
            $table->decimal('po_outstanding_qty', 20, 4)->nullable();
            $table->decimal('po_deviation_value', 20, 4)->nullable();
            $table->timestamps();

            $table->unique(['non_po_receipt_id', 'product_detail_id', 'uom_name'], 'non_po_receipt_items_identity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('non_po_receipt_items');
    }
};
