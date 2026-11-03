<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery assessment snapshot per Goods Receipt (docs/products/purchasing/vendor-compliance-late-delivery/prd.md §14.1).
 * Additive: historical receipts keep a null status and show as not yet assessed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->date('po_required_date')->nullable();
            $table->unsignedSmallInteger('delivery_delay_days')->nullable();
            $table->string('delivery_assessment_status', 20)->nullable()->index();
            $table->timestamp('delivery_assessed_at')->nullable();
            $table->string('delivery_assessment_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropIndex(['delivery_assessment_status']);
            $table->dropColumn(['po_required_date', 'delivery_delay_days', 'delivery_assessment_status', 'delivery_assessed_at', 'delivery_assessment_note']);
        });
    }
};
