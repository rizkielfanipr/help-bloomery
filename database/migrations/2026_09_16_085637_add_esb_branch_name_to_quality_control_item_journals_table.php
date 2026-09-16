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
        Schema::table('quality_control_item_journals', function (Blueprint $table) {
            $table->string('esb_branch_name')->nullable()->after('esb_branch_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quality_control_item_journals', function (Blueprint $table) {
            $table->dropColumn('esb_branch_name');
        });
    }
};
