<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A branch can now be linked to multiple ESB (branch code, comcode) pairs
     * — e.g. a physical branch recorded under more than one ESB company —
     * with results from every active pair summed/merged together wherever
     * ESB data is fetched. Moves the single esb_branch_code/esb_comcode
     * columns on `branches` into this child table (one row per existing
     * branch that had both set), then drops the old columns.
     */
    public function up(): void
    {
        Schema::create('branch_esb_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('esb_branch_code', 50);
            $table->string('esb_comcode', 50);
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'esb_branch_code', 'esb_comcode'], 'branch_esb_codes_unique_pair');
        });

        DB::table('branches')
            ->whereNotNull('esb_branch_code')
            ->where('esb_branch_code', '!=', '')
            ->whereNotNull('esb_comcode')
            ->where('esb_comcode', '!=', '')
            ->orderBy('id')
            ->get(['id', 'esb_branch_code', 'esb_comcode'])
            ->each(function (object $branch): void {
                DB::table('branch_esb_codes')->insert([
                    'branch_id' => $branch->id,
                    'esb_branch_code' => $branch->esb_branch_code,
                    'esb_comcode' => $branch->esb_comcode,
                    'label' => 'NO LABEL',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['esb_branch_code', 'esb_comcode']);
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('esb_branch_code', 50)->nullable()->after('name');
            $table->string('esb_comcode', 50)->nullable()->after('esb_branch_code');
        });

        DB::table('branch_esb_codes')
            ->orderBy('branch_id')
            ->orderBy('id')
            ->get()
            ->groupBy('branch_id')
            ->each(function ($pairs, $branchId): void {
                $first = $pairs->first();
                DB::table('branches')->where('id', $branchId)->update([
                    'esb_branch_code' => $first->esb_branch_code,
                    'esb_comcode' => $first->esb_comcode,
                ]);
            });

        Schema::dropIfExists('branch_esb_codes');
    }
};
