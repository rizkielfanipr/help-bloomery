<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/material-sourcing-lifecycle-prd.md §11: separate IT review, ESB sync, and pricing
 * dimensions next to the existing sourcing status. Additive only: existing rows keep
 * `review_status = null` / `esb_sync_status = null` (legacy, pre-workflow) and are never given
 * review history they did not have. The legacy `status` column stays authoritative for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_product_esb_materials', function (Blueprint $table): void {
            $table->string('review_status', 30)->nullable()->after('status');
            $table->unsignedInteger('submission_version')->default(0)->after('review_status');
            $table->string('esb_sync_status', 30)->nullable()->after('submission_version');
            $table->timestamp('esb_sync_claimed_at')->nullable()->after('esb_sync_status');
            $table->timestamp('esb_verified_at')->nullable()->after('esb_sync_claimed_at');
            $table->string('pricing_status', 30)->default('not_ready')->after('sourcing_status');
            $table->timestamp('sourcing_requested_at')->nullable()->after('pricing_status');
            $table->timestamp('workflow_completed_at')->nullable()->after('sourcing_requested_at');

            $table->index(['review_status', 'updated_at'], 'rnd_esb_materials_review_status_index');
            $table->index('esb_sync_status', 'rnd_esb_materials_esb_sync_status_index');
            $table->index('pricing_status', 'rnd_esb_materials_pricing_status_index');
            $table->index('sourcing_status', 'rnd_esb_materials_sourcing_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('rnd_product_esb_materials', function (Blueprint $table): void {
            $table->dropIndex('rnd_esb_materials_review_status_index');
            $table->dropIndex('rnd_esb_materials_esb_sync_status_index');
            $table->dropIndex('rnd_esb_materials_pricing_status_index');
            $table->dropIndex('rnd_esb_materials_sourcing_status_index');
            $table->dropColumn([
                'review_status', 'submission_version', 'esb_sync_status', 'esb_sync_claimed_at', 'esb_verified_at',
                'pricing_status', 'sourcing_requested_at', 'workflow_completed_at',
            ]);
        });
    }
};
