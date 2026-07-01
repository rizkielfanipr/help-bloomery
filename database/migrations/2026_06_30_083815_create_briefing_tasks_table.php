<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('briefing_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('period');
            $table->string('submission_type');
            $table->string('note_type')->default('');
            $table->string('group')->nullable();
            $table->string('group_label')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->time('deadline_time')->nullable();
            $table->unsignedTinyInteger('deadline_day')->nullable();
            $table->boolean('deadline_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('briefing_tasks');
    }
};
