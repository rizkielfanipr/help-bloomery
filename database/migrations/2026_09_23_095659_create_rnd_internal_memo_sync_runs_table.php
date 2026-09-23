<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_internal_memo_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_internal_memo_id')->constrained()->cascadeOnDelete();
            $table->string('company_code', 10)->default('BLSS');
            $table->string('status', 20)->default('pending');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->text('error_summary')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rnd_internal_memo_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_internal_memo_sync_runs');
    }
};
