<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchasing_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->nullOnDelete()->constrained('departments');
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('approved_by')->nullable()->nullOnDelete()->constrained('users');
            $table->text('keperluan');
            $table->string('status')->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchasing_requests');
    }
};
