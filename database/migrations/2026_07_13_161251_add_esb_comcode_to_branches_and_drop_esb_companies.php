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
        // Use a subquery to stay SQLite-compatible (MySQL JOIN-UPDATE not portable)
        $companies = DB::table('esb_companies')->pluck('comcode', 'id');
        foreach ($companies as $id => $comcode) {
            DB::table('branches')->where('esb_company_id', $id)->update(['esb_comcode' => $comcode]);
        }

        // Drop FK constraint first (before dropping esb_companies, so SQLite rebuild can still resolve the reference)
        if (DB::getDriverName() === 'sqlite') {
            // On SQLite, must be two separate closures: first rebuild removes the FK,
            // then the second closure can safely drop the column via ALTER TABLE DROP COLUMN.
            Schema::table('branches', function (Blueprint $table): void {
                $table->dropForeign(['esb_company_id']);
            });
            Schema::table('branches', function (Blueprint $table): void {
                $table->dropColumn('esb_company_id');
            });
        } else {
            Schema::table('branches', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('esb_company_id');
            });
        }

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
