<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casual_position_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('casual_position_opening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['casual_position_opening_id', 'user_id'], 'cpr_opening_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casual_position_registrations');
    }
};
