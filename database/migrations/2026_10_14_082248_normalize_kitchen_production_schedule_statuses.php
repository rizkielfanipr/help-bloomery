<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('kitchen_production_schedules')) {
            return;
        }

        DB::table('kitchen_production_schedules')
            ->whereIn('status', ['draft', 'published'])
            ->update(['status' => 'scheduled']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: a scheduled record cannot be classified safely as its former draft or
        // published state, so rolling back must not invent historical state.
    }
};
