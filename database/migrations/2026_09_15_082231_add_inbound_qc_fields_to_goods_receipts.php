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
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('delivery_number');
            $table->string('invoice_status')->default('received')->after('delivery_date');
            $table->string('invoice_number')->nullable()->after('invoice_status');
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->boolean('po_document_match')->default(true);
            $table->boolean('delivery_document_match')->default(true);
            $table->boolean('invoice_document_match')->default(true);
            $table->boolean('price_match')->default(true);
            $table->text('document_notes')->nullable();
            $table->json('document_evidence_photos')->nullable();
            $table->string('qc_outcome')->nullable()->index();
            $table->timestamp('qc_completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_date', 'invoice_status', 'invoice_number', 'invoice_date',
                'po_document_match', 'delivery_document_match', 'invoice_document_match',
                'price_match', 'document_notes', 'document_evidence_photos',
                'qc_outcome', 'qc_completed_at',
            ]);
        });
    }
};
