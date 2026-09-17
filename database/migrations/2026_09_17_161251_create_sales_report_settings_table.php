<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_report_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('auto_reject_enabled')->default(false);
            $table->unsignedInteger('auto_reject_after_days')->default(3);
            $table->string('auto_reject_reason', 255);
            $table->date('effective_from');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_report_settings');
    }
};
