<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('briefing_tasks', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('briefing_tasks', function (Blueprint $table) {
            $table->dropForeignIdFor(Branch::class);
            $table->dropColumn('branch_id');
        });
    }
};
