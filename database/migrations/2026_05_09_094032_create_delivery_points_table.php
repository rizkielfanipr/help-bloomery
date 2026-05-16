<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_log_id')->constrained('driver_logs')->cascadeOnDelete();
            $table->unsignedTinyInteger('urutan');
            $table->string('nama_toko');
            $table->text('alamat');
            $table->time('waktu_tiba')->nullable();
            $table->time('waktu_selesai')->nullable();
            $table->unsignedInteger('jumlah_produk')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_points');
    }
};
