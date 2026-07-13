<?php

use App\Models\EsbCompany;
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
        Schema::table('branches', function (Blueprint $table): void {
            $table->foreignId('esb_company_id')->nullable()->after('esb_branch_code')->constrained('esb_companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropForeignIdFor(EsbCompany::class);
            $table->dropColumn('esb_company_id');
        });
    }
};
