<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_repair_requests', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('requester_id')->nullOnDelete()->constrained('branches');
            $table->foreignId('erp_module_id')->nullable()->after('assignee_id')->nullOnDelete()->constrained('erp_modules');
            $table->renameColumn('catatan_perbaikan', 'keterangan');
            $table->dropColumn(['jenis_modul_erp', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::table('erp_repair_requests', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'erp_module_id']);
            $table->renameColumn('keterangan', 'catatan_perbaikan');
            $table->string('jenis_modul_erp')->nullable();
            $table->string('priority')->default('medium');
        });
    }
};
