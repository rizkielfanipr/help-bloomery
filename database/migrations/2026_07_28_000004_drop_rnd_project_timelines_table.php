<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rnd_project_timelines');
    }

    public function down(): void
    {
        Schema::create('rnd_project_timelines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 30)->default('planned');
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
            $table->index(['rnd_project_id', 'sort_order']);
        });
    }
};
