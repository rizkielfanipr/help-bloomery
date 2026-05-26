<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_templates', function (Blueprint $table) {
            // JSON array of field definitions: [{key, label, type, required, options?}]
            $table->json('form_schema')->nullable()->after('description');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            // Stores technician's answers for dynamic template fields
            $table->json('before_data')->nullable()->after('before_notes');
            $table->json('after_data')->nullable()->after('after_notes');
        });
    }

    public function down(): void
    {
        Schema::table('service_templates', function (Blueprint $table) {
            $table->dropColumn('form_schema');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn(['before_data', 'after_data']);
        });
    }
};
