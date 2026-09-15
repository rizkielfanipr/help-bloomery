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
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('company_code', 10)->default('BLSS');
            $table->string('reference_number')->index();
            $table->string('esb_goods_receipt_number')->nullable()->unique();
            $table->date('purchase_date')->nullable();
            $table->date('goods_receipt_date');
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('branch_name')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_name')->nullable();
            $table->unsignedBigInteger('location_id')->index();
            $table->string('location_name');
            $table->string('delivery_number')->nullable();
            $table->text('additional_info')->nullable();
            $table->string('selected_asset_ids')->nullable();
            $table->boolean('auto_close_po')->default(false);
            $table->string('status', 20)->default('processing')->index();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('esb_code')->nullable();
            $table->text('esb_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
