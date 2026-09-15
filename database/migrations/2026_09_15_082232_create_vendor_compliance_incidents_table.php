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
        Schema::create('vendor_compliance_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number')->unique();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->string('supplier_name')->nullable()->index();
            $table->string('category')->index();
            $table->string('severity')->index();
            $table->unsignedSmallInteger('demerit_points');
            $table->decimal('affected_quantity', 20, 4);
            $table->string('status')->default('open')->index();
            $table->text('description');
            $table->json('evidence_photos')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_compliance_incidents');
    }
};
