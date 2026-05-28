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
        Schema::create('service_request_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->nullOnDelete()->constrained('users');
            $table->unsignedTinyInteger('cycle')->default(1);
            $table->string('before_photo')->nullable();
            $table->text('before_notes')->nullable();
            $table->string('after_photo')->nullable();
            $table->text('after_notes')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('warranty_expires_at')->nullable();
            $table->timestamps();
        });

        // Drop per-repair columns from service_requests (now live in repairs table)
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn([
                'before_photo', 'before_notes',
                'after_photo', 'after_notes',
                'started_at', 'completed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_repairs');

        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('before_photo')->nullable();
            $table->text('before_notes')->nullable();
            $table->string('after_photo')->nullable();
            $table->text('after_notes')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
        });
    }
};
