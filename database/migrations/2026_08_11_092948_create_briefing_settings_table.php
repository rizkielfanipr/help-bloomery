<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('briefing_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('auto_reject_after_days')->default(3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('briefing_settings');
    }
};
