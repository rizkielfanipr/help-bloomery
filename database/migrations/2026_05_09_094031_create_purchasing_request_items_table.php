<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchasing_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchasing_request_id')->constrained('purchasing_requests')->cascadeOnDelete();
            $table->string('nama_barang');
            $table->decimal('jumlah', 10, 2);
            $table->string('satuan');
            $table->text('spesifikasi')->nullable();
            $table->decimal('estimated_price', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchasing_request_items');
    }
};
