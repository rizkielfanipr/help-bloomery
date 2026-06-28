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
        Schema::table('design_requests', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('requester_id')->nullOnDelete()->constrained('branches');
            $table->foreignId('design_category_id')->nullable()->after('assignee_id')->nullOnDelete()->constrained('design_categories');
            $table->dropColumn('kategori_desain');
        });
    }

    public function down(): void
    {
        Schema::table('design_requests', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['design_category_id']);
            $table->dropColumn(['branch_id', 'design_category_id']);
            $table->string('kategori_desain')->default('');
        });
    }
};
