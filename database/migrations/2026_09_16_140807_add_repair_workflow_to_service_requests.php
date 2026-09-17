<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->date('scheduled_date')->nullable()->change();
            $table->string('priority')->default('normal');
            $table->text('diagnosis')->nullable();
            $table->string('asset_condition')->nullable();
            $table->text('outsource_reason')->nullable();
            $table->json('outsource_report')->nullable();
            $table->text('verification_notes')->nullable();
        });
        Schema::table('service_request_repairs', function (Blueprint $table): void {
            $table->json('outsource_report')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_request_repairs', function (Blueprint $table): void {
            $table->dropColumn('outsource_report');
        });
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropColumn(['priority', 'diagnosis', 'asset_condition', 'outsource_reason', 'outsource_report', 'verification_notes']);
        });
    }
};
