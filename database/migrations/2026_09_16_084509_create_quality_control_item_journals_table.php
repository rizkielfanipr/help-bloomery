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
        Schema::create('quality_control_item_journals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('esb_branch_id');
            $table->string('esb_branch_code', 50);
            $table->string('esb_comcode', 50);
            $table->unsignedBigInteger('location_id');
            $table->string('location_name');
            $table->date('journal_date');
            $table->text('additional_info')->nullable();
            $table->string('status', 40)->default('draft');
            $table->string('item_journal_number', 100)->nullable()->unique();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_control_item_journals');
    }
};
