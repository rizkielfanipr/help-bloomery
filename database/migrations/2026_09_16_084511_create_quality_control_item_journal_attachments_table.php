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
        Schema::create('quality_control_item_journal_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('quality_control_item_journal_id');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->text('esb_url')->nullable();
            $table->timestamp('uploaded_to_esb_at')->nullable();
            $table->timestamps();
            $table->foreign('quality_control_item_journal_id', 'qc_item_journal_attachment_journal_fk')->references('id')->on('quality_control_item_journals')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_control_item_journal_attachments');
    }
};
