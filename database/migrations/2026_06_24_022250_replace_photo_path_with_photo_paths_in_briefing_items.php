<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('briefing_items', function (Blueprint $table): void {
            $table->json('photo_paths')->nullable()->after('task_key');
        });

        DB::statement("
            UPDATE briefing_items
            SET photo_paths = JSON_ARRAY(photo_path)
            WHERE photo_path IS NOT NULL AND photo_path != ''
        ");

        Schema::table('briefing_items', function (Blueprint $table): void {
            $table->dropColumn('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('briefing_items', function (Blueprint $table): void {
            $table->string('photo_path')->nullable()->after('task_key');
        });

        DB::statement("
            UPDATE briefing_items
            SET photo_path = JSON_UNQUOTE(JSON_EXTRACT(photo_paths, '$[0]'))
            WHERE photo_paths IS NOT NULL
        ");

        Schema::table('briefing_items', function (Blueprint $table): void {
            $table->dropColumn('photo_paths');
        });
    }
};
