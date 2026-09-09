<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('store_sops', function (Blueprint $table): void {
            $table->foreignId('brand_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('store_sop_category_id')->nullable()->after('brand_id')->constrained()->nullOnDelete();
        });

        DB::table('store_sops')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->each(function (string $category): void {
                $categoryId = DB::table('store_sop_categories')->insertGetId([
                    'name' => $category,
                    'is_active' => true,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('store_sops')->where('category', $category)->update(['store_sop_category_id' => $categoryId]);
            });

        Schema::table('store_sops', function (Blueprint $table): void {
            $table->dropColumn(['version', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_sops', function (Blueprint $table): void {
            $table->string('version', 30)->default('1.0')->after('title');
            $table->string('category', 100)->nullable()->after('version');
        });

        DB::table('store_sops')
            ->join('store_sop_categories', 'store_sop_categories.id', '=', 'store_sops.store_sop_category_id')
            ->update(['store_sops.category' => DB::raw('store_sop_categories.name')]);

        Schema::table('store_sops', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('store_sop_category_id');
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};
