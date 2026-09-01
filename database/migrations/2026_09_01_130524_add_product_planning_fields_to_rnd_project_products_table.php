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
        Schema::table('rnd_project_products', function (Blueprint $table) {
            $table->unsignedSmallInteger('shelf_life_value')->nullable()->after('release_date');
            $table->string('shelf_life_unit', 20)->nullable()->after('shelf_life_value');
            $table->string('storage_condition', 30)->nullable()->after('shelf_life_unit');
            $table->text('storage_notes')->nullable()->after('storage_condition');
            $table->unsignedInteger('target_outlets')->nullable()->after('storage_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_project_products', function (Blueprint $table) {
            $table->dropColumn([
                'shelf_life_value',
                'shelf_life_unit',
                'storage_condition',
                'storage_notes',
                'target_outlets',
            ]);
        });
    }
};
