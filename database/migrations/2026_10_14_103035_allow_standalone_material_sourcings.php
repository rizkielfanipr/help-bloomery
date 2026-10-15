<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets Sourcing Bahan record a supplier sample that does not come from a Project R&D product.
 * Such a standalone row has no Project product (nor the ESB category/sub-category/UOM IDs a Project
 * material is created with), so those columns become nullable, and `sourcing_origin` tells the two
 * apart. The material itself is picked from Master Product ESB, so its ESB product detail and code
 * are kept next to the existing `esb_product_id`. Existing rows keep the `project` default; nothing
 * is backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_product_esb_materials', function (Blueprint $table): void {
            $table->unsignedBigInteger('rnd_project_product_id')->nullable()->change();
            $table->unsignedBigInteger('category_id')->nullable()->change();
            $table->unsignedBigInteger('sub_category_id')->nullable()->change();
            $table->unsignedBigInteger('uom_id')->nullable()->change();
            $table->string('sourcing_origin', 20)->default('project')->after('sourcing_status')->index();
            $table->unsignedBigInteger('esb_product_detail_id')->nullable()->after('esb_product_id')->index();
            $table->string('esb_product_code', 50)->nullable()->after('esb_product_detail_id');
        });
    }

    /**
     * Restoring NOT NULL would fail on (or silently corrupt) standalone rows, so the rollback refuses
     * while any exist instead of deleting them.
     */
    public function down(): void
    {
        $standaloneRows = DB::table('rnd_product_esb_materials')
            ->where('sourcing_origin', 'standalone')
            ->orWhereNull('rnd_project_product_id')
            ->count();

        if ($standaloneRows > 0) {
            throw new RuntimeException("Tidak dapat rollback: masih ada {$standaloneRows} sourcing tanpa Project. Pindahkan atau arsipkan data tersebut terlebih dahulu.");
        }

        Schema::table('rnd_product_esb_materials', function (Blueprint $table): void {
            $table->dropIndex(['sourcing_origin']);
            $table->dropIndex(['esb_product_detail_id']);
            $table->dropColumn(['sourcing_origin', 'esb_product_detail_id', 'esb_product_code']);
            $table->unsignedBigInteger('rnd_project_product_id')->nullable(false)->change();
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
            $table->unsignedBigInteger('sub_category_id')->nullable(false)->change();
            $table->unsignedBigInteger('uom_id')->nullable(false)->change();
        });
    }
};
