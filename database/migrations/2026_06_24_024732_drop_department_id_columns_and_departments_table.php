<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        $tables = [
            'service_requests',
            'purchasing_requests',
            'design_requests',
            'erp_repair_requests',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table, $isSqlite) {
                if (! $isSqlite) {
                    $foreignKeys = array_column(DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'", [$table]), 'CONSTRAINT_NAME');
                    if (in_array("{$table}_department_id_foreign", $foreignKeys)) {
                        $t->dropForeign("{$table}_department_id_foreign");
                    }
                }
                if (Schema::hasColumn($table, 'department_id')) {
                    $t->dropColumn('department_id');
                }
            });
        }

        Schema::dropIfExists('departments');
    }

    public function down(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        $tables = [
            'service_requests',
            'purchasing_requests',
            'design_requests',
            'erp_repair_requests',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('department_id')->nullable()->constrained('departments');
            });
        }
    }
};
