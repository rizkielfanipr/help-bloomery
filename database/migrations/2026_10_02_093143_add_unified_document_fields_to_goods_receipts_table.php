<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/receiving-simplification-prd.md §5.2, §15. Additive only — `delivery_number`,
     * `invoice_number`, `delivery_date`, `invoice_date`, `invoice_status`, and
     * `document_evidence_photos` are kept so existing reports keep reading them.
     * `GoodsReceiptPage` fills both the new and the legacy columns on submit.
     */
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('document_type')->nullable()->after('invoice_date');
            $table->string('document_number')->nullable()->after('document_type');
            $table->date('document_date')->nullable()->after('document_number');
            $table->json('document_photos')->nullable()->after('document_evidence_photos');
            $table->json('goods_photos')->nullable()->after('document_photos');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'document_number', 'document_date', 'document_photos', 'goods_photos']);
        });
    }
};
