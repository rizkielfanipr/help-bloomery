<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** docs/store-sales-order-prd.md §15.1. */
    public function up(): void
    {
        Schema::create('store_sales_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_esb_code_id')->nullable()->constrained('branch_esb_codes')->nullOnDelete();
            $table->string('company_code_snapshot', 10);
            $table->string('branch_code_snapshot', 50);
            $table->unsignedBigInteger('esb_branch_id_snapshot');
            $table->string('branch_name_snapshot');
            $table->string('product_sales_number', 100);
            $table->dateTime('product_sales_date')->nullable();
            $table->dateTime('required_date')->nullable();
            $table->string('customer_id_snapshot')->nullable();
            $table->string('customer_name_snapshot')->nullable();
            $table->text('customer_address_snapshot')->nullable();
            $table->decimal('product_sales_total', 18, 2)->nullable();
            $table->string('currency_sign', 10)->nullable();
            $table->string('esb_status_id')->nullable();
            $table->string('esb_status_name')->nullable();
            $table->string('esb_created_by')->nullable();
            $table->string('link_purchase_number')->nullable();
            $table->text('esb_additional_info')->nullable();
            $table->json('esb_snapshot')->nullable();
            $table->timestamp('last_verified_at');
            $table->string('phone_number', 50)->nullable();
            $table->string('ordered_by', 150)->nullable();
            $table->string('event_type', 30)->nullable();
            $table->string('event_type_other')->nullable();
            $table->time('delivery_time')->nullable();
            $table->text('preparation_notes')->nullable();
            $table->json('attachment_paths')->nullable();
            $table->string('operational_status', 20)->default('draft');
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Soft-delete-safe uniqueness via a generated column (MySQL has no partial unique
            // index): collapses to NULL once deleted_at is set, and MySQL/SQLite both treat NULL
            // as always-distinct, so a soft-deleted record never blocks a new one for the same
            // number (docs/store-sales-order-prd.md §14 "unik untuk record aktif").
            $table->string('product_sales_number_if_active', 100)
                ->nullable()
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN product_sales_number ELSE NULL END');

            $table->index('branch_id');
            $table->index('branch_esb_code_id');
            $table->index('product_sales_number');
            $table->index('product_sales_date');
            $table->index('required_date');
            $table->index('customer_name_snapshot');
            $table->index('operational_status');
            $table->index('submitted_by');
            $table->unique(
                ['company_code_snapshot', 'esb_branch_id_snapshot', 'product_sales_number_if_active'],
                'store_sales_orders_active_number_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_sales_orders');
    }
};
