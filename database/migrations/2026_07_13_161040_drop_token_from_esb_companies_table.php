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
        Schema::table('esb_companies', function (Blueprint $table): void {
            $table->dropColumn('token');
        });
    }

    public function down(): void
    {
        Schema::table('esb_companies', function (Blueprint $table): void {
            $table->string('token')->default('');
        });
    }
};
