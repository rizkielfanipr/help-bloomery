<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('esb_comcode', 50)->nullable()->after('esb_branch_code');
        });

        // Migrate comcode values from esb_companies into branches
        DB::table('branches')
            ->join('esb_companies', 'branches.esb_company_id', '=', 'esb_companies.id')
            ->update(['branches.esb_comcode' => DB::raw('esb_companies.comcode')]);

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('esb_company_id');
        });

        Schema::dropIfExists('esb_companies');
    }

    public function down(): void
    {
        Schema::create('esb_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('comcode')->unique();
            $table->timestamps();
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->foreignId('esb_company_id')->nullable()->constrained('esb_companies')->nullOnDelete();
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('esb_comcode');
        });
    }
};
