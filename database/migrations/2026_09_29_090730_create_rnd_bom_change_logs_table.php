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
        Schema::create('rnd_bom_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('esb_bom_id');
            $table->string('bom_code')->nullable();
            $table->string('bom_name')->nullable();
            $table->string('product_code')->nullable();
            $table->string('product_name')->nullable();
            $table->string('source', 30);
            $table->string('event', 60);
            $table->string('status', 30);
            $table->text('reason')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('requested_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->json('changes')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->string('esb_edited_at_before')->nullable();
            $table->string('esb_edited_at_after')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->index('esb_bom_id');
            $table->index('status');
            $table->index('source');
            $table->index('changed_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_bom_change_logs');
    }
};
