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
        Schema::table('customer_complaints', function (Blueprint $table) {
            $table->index('branch_id');
            $table->dropIndex(['branch_id', 'status']);
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });

        Schema::dropIfExists('customer_complaint_activities');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_complaints', function (Blueprint $table) {
            $table->string('status', 20)->default('new')->after('attachment_paths');
            $table->index(['branch_id', 'status']);
            $table->index('status');
            $table->dropIndex(['branch_id']);
        });

        Schema::create('customer_complaint_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_complaint_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type', 30);
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('customer_complaint_id');
        });
    }
};
