<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_requests')
            ->where('status', 'in_process')
            ->update(['status' => 'approved']);
    }

    public function down(): void
    {
        DB::table('purchase_requests')
            ->where('status', 'approved')
            ->update(['status' => 'in_process']);
    }
};
