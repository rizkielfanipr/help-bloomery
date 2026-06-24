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
                    $t->dropForeign("{$table}_department_id_foreign");
                }
                $t->dropColumn('department_id');
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
