<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Void records of a Sales Report shift (docs/products/finance/sales-report-compliment-void-remediation/prd.md
 * §10.6). A separate domain from Compliment; nothing is backfilled from older data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_report_voids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_report_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('shift_number');
            $table->string('sales_number', 50);
            $table->string('sales_number_source', 20);
            $table->string('source_comcode', 50)->nullable();
            $table->string('source_branch_code', 50)->nullable();
            $table->dateTime('sales_transaction_at')->nullable();
            $table->decimal('transaction_amount', 15, 2)->nullable();
            $table->string('payment_method_id', 100)->nullable();
            $table->string('payment_method_name', 100);
            $table->string('payment_method_source', 20);
            $table->text('reason');
            $table->string('original_receipt_path');
            $table->string('voided_receipt_path');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sales_report_id', 'shift_number']);
            $table->index('sales_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_report_voids');
    }
};
