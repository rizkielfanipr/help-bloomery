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
        Schema::table('rnd_internal_memo_materials', function (Blueprint $table): void {
            $table->unsignedBigInteger('purchasing_uom_id')->nullable()->after('purchase_uom_name');
            $table->string('purchasing_uom_name')->nullable()->after('purchasing_uom_id');
        });

        Schema::table('rnd_internal_memo_extra_products', function (Blueprint $table): void {
            $table->unsignedBigInteger('purchasing_uom_id')->nullable()->after('purchase_uom_name');
            $table->string('purchasing_uom_name')->nullable()->after('purchasing_uom_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_internal_memo_extra_products', function (Blueprint $table): void {
            $table->dropColumn(['purchasing_uom_id', 'purchasing_uom_name']);
        });

        Schema::table('rnd_internal_memo_materials', function (Blueprint $table): void {
            $table->dropColumn(['purchasing_uom_id', 'purchasing_uom_name']);
        });
    }
};
