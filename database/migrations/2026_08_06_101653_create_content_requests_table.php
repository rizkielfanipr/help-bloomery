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
        Schema::create('content_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('branch_id')->nullable()->nullOnDelete()->constrained('branches');
            $table->string('judul_konten');
            $table->string('jenis_konten');
            $table->string('platform_tujuan')->nullable();
            $table->text('tujuan_konten');
            $table->string('link_contoh_konten')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status')->default('submitted');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_requests');
    }
};
