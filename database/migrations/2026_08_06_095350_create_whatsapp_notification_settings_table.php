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
        Schema::create('whatsapp_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('module_key')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->foreignId('pic_user_id')->nullable()->nullOnDelete()->constrained('users');
            $table->text('message_template');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notification_settings');
    }
};
