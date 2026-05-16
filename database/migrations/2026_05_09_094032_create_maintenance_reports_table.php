<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_schedule_id')->unique()->constrained('maintenance_schedules')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users');
            $table->text('kondisi_sebelum');
            $table->text('kondisi_sesudah');
            $table->text('tindakan_perbaikan');
            $table->json('before_photos')->nullable();
            $table->json('after_photos')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_reports');
    }
};
