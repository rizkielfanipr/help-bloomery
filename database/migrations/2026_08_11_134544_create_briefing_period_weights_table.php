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
        Schema::create('briefing_period_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->decimal('daily_weight', 5, 2)->default(40);
            $table->decimal('weekly_weight', 5, 2)->default(30);
            $table->decimal('monthly_weight', 5, 2)->default(30);
            $table->timestamps();
        });

        // Seed the global default row (branch_id null) so BriefingPeriodWeight::forBranch()
        // always has a fallback, even before any admin visits the settings page.
        DB::table('briefing_period_weights')->insert([
            'branch_id' => null,
            'daily_weight' => 40,
            'weekly_weight' => 30,
            'monthly_weight' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('briefing_period_weights');
    }
};
