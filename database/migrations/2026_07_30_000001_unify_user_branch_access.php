<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_branches', function (Blueprint $table): void {
            $table->boolean('is_primary')->default(false)->after('branch_id');
        });

        DB::table('users')
            ->whereNotNull('branch_id')
            ->orderBy('id')
            ->each(function (object $user): void {
                DB::table('user_branches')->updateOrInsert(
                    ['user_id' => $user->id, 'branch_id' => $user->branch_id],
                    ['is_primary' => true],
                );
            });
    }

    public function down(): void
    {
        Schema::table('user_branches', function (Blueprint $table): void {
            $table->dropColumn('is_primary');
        });
    }
};
