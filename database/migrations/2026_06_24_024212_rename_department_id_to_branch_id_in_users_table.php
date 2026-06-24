<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('users_department_id_foreign');
            }
            $table->renameColumn('department_id', 'branch_id');
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('users_branch_id_foreign');
            }
            $table->renameColumn('branch_id', 'department_id');
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            }
        });
    }
};
