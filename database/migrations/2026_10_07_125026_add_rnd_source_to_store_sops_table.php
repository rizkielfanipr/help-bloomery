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
        Schema::table('store_sops', function (Blueprint $table): void {
            $table->foreignId('source_rnd_project_id')->nullable()->after('store_sop_category_id')->constrained('rnd_projects')->nullOnDelete();
            $table->foreignId('source_rnd_project_product_id')->nullable()->after('source_rnd_project_id')->constrained('rnd_project_products')->nullOnDelete();
            $table->string('source_scope', 20)->nullable()->after('source_rnd_project_product_id');
            $table->string('source_release_key', 64)->nullable()->unique()->after('source_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_sops', function (Blueprint $table): void {
            $table->dropUnique(['source_release_key']);
            $table->dropConstrainedForeignId('source_rnd_project_product_id');
            $table->dropConstrainedForeignId('source_rnd_project_id');
            $table->dropColumn(['source_scope', 'source_release_key']);
        });
    }
};
