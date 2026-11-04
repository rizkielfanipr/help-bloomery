<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Header-level Late Delivery incidents next to the item-level QC incidents
 * (docs/products/purchasing/vendor-compliance-late-delivery/prd.md §14.2/§20). Existing incidents keep
 * their item, quantity, points, and status and become `liability_status = not_applicable`.
 *
 * The item unique index is replaced by a plain index first: on MySQL the foreign key needs an index
 * on the column at every step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_compliance_incidents', function (Blueprint $table) {
            $table->string('source_type', 30)->nullable();
            $table->string('source_key')->nullable()->unique('vci_source_key_unique');
            $table->string('liability_status', 20)->default('not_applicable');
            $table->foreignId('liability_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('liability_reviewed_at')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->date('required_date')->nullable();
            $table->date('first_late_receipt_date')->nullable();
            $table->date('latest_late_receipt_date')->nullable();
            $table->unsignedSmallInteger('delay_days')->nullable();
            $table->string('scoring_rule_version', 30)->nullable();

            $table->index(['category', 'liability_status'], 'vci_category_liability_index');
            $table->index(['status', 'occurred_at'], 'vci_status_occurred_index');
            $table->index('goods_receipt_item_id', 'vci_goods_receipt_item_index');
        });

        DB::table('vendor_compliance_incidents')->update(['liability_status' => 'not_applicable']);

        Schema::table('vendor_compliance_incidents', function (Blueprint $table) {
            $table->dropUnique('vendor_compliance_incidents_goods_receipt_item_id_unique');
        });

        Schema::table('vendor_compliance_incidents', function (Blueprint $table) {
            $table->unsignedBigInteger('goods_receipt_item_id')->nullable()->change();
            $table->decimal('affected_quantity', 20, 4)->nullable()->change();
        });

        Schema::create('vendor_compliance_incident_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_compliance_incident_id')->constrained(indexName: 'vci_events_incident_fk')->restrictOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_liability_status', 20)->nullable();
            $table->string('to_liability_status', 20)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Development only: the item unique index cannot come back once header-level or multiple
     * incidents per item exist, so rollback refuses instead of deleting them.
     */
    public function down(): void
    {
        $blocking = DB::table('vendor_compliance_incidents')->whereNull('goods_receipt_item_id')->exists()
            || DB::table('vendor_compliance_incidents')->select('goods_receipt_item_id')->whereNotNull('goods_receipt_item_id')
                ->groupBy('goods_receipt_item_id')->havingRaw('count(*) > 1')->exists();
        if ($blocking) {
            throw new RuntimeException('Late Delivery atau insiden ganda per item sudah ada; rollback schema ditolak.');
        }

        Schema::dropIfExists('vendor_compliance_incident_events');

        Schema::table('vendor_compliance_incidents', function (Blueprint $table) {
            $table->unsignedBigInteger('goods_receipt_item_id')->nullable(false)->change();
            $table->decimal('affected_quantity', 20, 4)->nullable(false)->change();
            $table->unique('goods_receipt_item_id', 'vendor_compliance_incidents_goods_receipt_item_id_unique');
        });

        Schema::table('vendor_compliance_incidents', function (Blueprint $table) {
            $table->dropIndex('vci_goods_receipt_item_index');
            $table->dropIndex('vci_status_occurred_index');
            $table->dropIndex('vci_category_liability_index');
            $table->dropConstrainedForeignId('liability_reviewed_by');
            $table->dropUnique('vci_source_key_unique');
            $table->dropColumn(['source_type', 'source_key', 'liability_status', 'liability_reviewed_at', 'waiver_reason', 'required_date',
                'first_late_receipt_date', 'latest_late_receipt_date', 'delay_days', 'scoring_rule_version']);
        });
    }
};
