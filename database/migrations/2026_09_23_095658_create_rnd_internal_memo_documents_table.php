<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_internal_memo_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_internal_memo_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('disk', 30);
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->string('checksum');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['rnd_internal_memo_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_internal_memo_documents');
    }
};
