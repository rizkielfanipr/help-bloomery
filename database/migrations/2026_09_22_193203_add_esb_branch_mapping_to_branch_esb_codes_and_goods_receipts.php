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
        Schema::table('branch_esb_codes', function (Blueprint $table): void {
            $table->unsignedBigInteger('esb_branch_id')->nullable()->after('branch_id');
            $table->index(['esb_comcode', 'esb_branch_id'], 'branch_esb_codes_comcode_esb_id_index');
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->renameColumn('branch_id', 'esb_branch_id');
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignId('local_branch_id')->nullable()->after('esb_branch_id')->constrained('branches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('local_branch_id');
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->renameColumn('esb_branch_id', 'branch_id');
        });

        Schema::table('branch_esb_codes', function (Blueprint $table): void {
            $table->dropIndex('branch_esb_codes_comcode_esb_id_index');
            $table->dropColumn('esb_branch_id');
        });
    }
};
