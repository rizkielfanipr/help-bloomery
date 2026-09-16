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
        Schema::create('quality_control_item_journal_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('quality_control_item_journal_id');
            $table->unsignedBigInteger('product_detail_id');
            $table->string('product_code')->nullable();
            $table->string('product_name');
            $table->string('uom_name', 50)->nullable();
            $table->unsignedBigInteger('purpose_id')->nullable();
            $table->string('purpose_name')->nullable();
            $table->string('purpose_account')->nullable();
            $table->decimal('qty', 18, 4);
            $table->decimal('hpp', 18, 4)->nullable();
            $table->timestamps();

            $table->unique(['quality_control_item_journal_id', 'product_detail_id'], 'qc_item_journal_product_unique');
            $table->foreign('quality_control_item_journal_id', 'qc_item_journal_detail_journal_fk')->references('id')->on('quality_control_item_journals')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_control_item_journal_details');
    }
};
