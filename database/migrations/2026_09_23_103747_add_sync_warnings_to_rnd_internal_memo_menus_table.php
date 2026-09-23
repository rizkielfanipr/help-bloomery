<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 correction (docs/rnd-internal-memo-prd.md §15): `sync_error` alone cannot separate a
 * hard blocker from a non-blocking warning, which the validation/finalization gate needs to
 * distinguish (§15.1 vs §15.2). `sync_error` keeps only blocker-level messages from here on;
 * `sync_warnings` holds the rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_internal_memo_menus', function (Blueprint $table): void {
            $table->json('sync_warnings')->nullable()->after('sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('rnd_internal_memo_menus', function (Blueprint $table): void {
            $table->dropColumn('sync_warnings');
        });
    }
};
