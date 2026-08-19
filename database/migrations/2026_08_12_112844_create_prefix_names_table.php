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
        Schema::create('prefix_names', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the options that were previously hardcoded in
        // ViewProjectProductPage::esbMaterialNamePrefixOptions() so existing
        // WIP material naming keeps working once it reads from this table.
        $now = now();
        DB::table('prefix_names')->insert([
            ['code' => 'WIP |', 'label' => 'Kitchen - WIP |', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'JYM |', 'label' => 'Joy-Mart - JYM |', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ATL |', 'label' => 'Atelier - ATL |', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EGG |', 'label' => 'Eggish - EGG |', 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'Central |', 'label' => 'Patisserie - Central |', 'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prefix_names');
    }
};
