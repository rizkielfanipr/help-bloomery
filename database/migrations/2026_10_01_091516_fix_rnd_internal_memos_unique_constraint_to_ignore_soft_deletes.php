<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original unique index on (company_code, period_month, revision) applies to every row
     * at the database level, including soft-deleted ones — MySQL does not support partial/
     * filtered unique indexes the way PostgreSQL does. Deleting a Memo (now always allowed
     * regardless of legacy status, see RndInternalMemoPolicy::delete()) and then creating a new
     * one for the same period therefore hit a raw UniqueConstraintViolationException even though
     * the application-level duplicate check correctly ignores soft-deleted records.
     *
     * The fix is a generated column that collapses to NULL once a row is soft-deleted; MySQL and
     * SQLite both treat NULL as distinct from any other value (including other NULLs) in a unique
     * index, so soft-deleted rows never collide with each other or with an active row, while
     * active rows (deleted_at IS NULL) are still uniquely constrained exactly as before.
     */
    public function up(): void
    {
        Schema::table('rnd_internal_memos', function (Blueprint $table): void {
            $table->dropUnique('rnd_internal_memos_company_code_period_month_revision_unique');
        });

        Schema::table('rnd_internal_memos', function (Blueprint $table): void {
            $table->date('period_month_if_active')
                ->nullable()
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN period_month ELSE NULL END')
                ->after('revision');
        });

        Schema::table('rnd_internal_memos', function (Blueprint $table): void {
            $table->unique(['company_code', 'period_month_if_active', 'revision'], 'rnd_internal_memos_active_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_internal_memos', function (Blueprint $table): void {
            $table->dropUnique('rnd_internal_memos_active_period_unique');
            $table->dropColumn('period_month_if_active');
        });

        Schema::table('rnd_internal_memos', function (Blueprint $table): void {
            $table->unique(['company_code', 'period_month', 'revision']);
        });
    }
};
