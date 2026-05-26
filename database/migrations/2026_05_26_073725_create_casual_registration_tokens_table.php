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
        Schema::create('casual_registration_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 10)->unique();
            $table->string('label')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('used_by')->nullable()->nullOnDelete()->constrained('users');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->nullOnDelete()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('casual_registration_tokens');
    }
};
