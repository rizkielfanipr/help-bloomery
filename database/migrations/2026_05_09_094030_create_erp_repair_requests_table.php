<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_repair_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->nullOnDelete()->constrained('departments');
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('assignee_id')->nullable()->nullOnDelete()->constrained('users');
            $table->string('jenis_modul_erp');
            $table->text('catatan_perbaikan');
            $table->json('attachments')->nullable();
            $table->string('status')->default('draft');
            $table->string('priority')->default('medium');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_repair_requests');
    }
};
