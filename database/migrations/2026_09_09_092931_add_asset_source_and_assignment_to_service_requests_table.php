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
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->string('source', 30)->default('manual')->after('asset_id');
            $table->timestamp('assigned_at')->nullable()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('asset_id');
            $table->dropColumn(['source', 'assigned_at']);
        });
    }
};
