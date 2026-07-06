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
        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedInteger('odo_start')->nullable()->after('notes');
            $table->string('odo_start_photo')->nullable()->after('odo_start');
            $table->unsignedInteger('odo_end')->nullable()->after('odo_start_photo');
            $table->string('odo_end_photo')->nullable()->after('odo_end');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['odo_start', 'odo_start_photo', 'odo_end', 'odo_end_photo']);
        });
    }
};
