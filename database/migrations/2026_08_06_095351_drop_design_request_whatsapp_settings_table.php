<?php

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
        Schema::dropIfExists('design_request_whatsapp_settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('design_request_whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->nullable();
            $table->text('message_template');
            $table->timestamps();
        });
    }
};
