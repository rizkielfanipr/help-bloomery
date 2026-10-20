<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_project_partnership_branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['rnd_project_id', 'branch_id'], 'rnd_project_partnership_branch_unique');
            $table->index(['branch_id', 'rnd_project_id'], 'rnd_project_partnership_branch_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_project_partnership_branches');
    }
};
