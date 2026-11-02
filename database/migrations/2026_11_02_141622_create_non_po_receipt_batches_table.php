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
        Schema::create('non_po_receipt_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('non_po_receipt_item_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('manufactured_date')->nullable();
            $table->date('expired_date')->nullable();
            $table->decimal('quantity', 20, 4)->default(0);
            $table->decimal('accepted_qty', 20, 4)->default(0);
            $table->decimal('hold_qty', 20, 4)->default(0);
            $table->decimal('rejected_qty', 20, 4)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('non_po_receipt_batches');
    }
};
