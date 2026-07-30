<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_project_boms', function (Blueprint $table): void {
            $table->json('detail_snapshot')->nullable()->after('sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('rnd_project_boms', function (Blueprint $table): void {
            $table->dropColumn('detail_snapshot');
        });
    }
};
