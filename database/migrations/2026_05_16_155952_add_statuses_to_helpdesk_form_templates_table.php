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
        Schema::table('helpdesk_form_templates', function (Blueprint $table) {
            $table->json('statuses')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_form_templates', function (Blueprint $table) {
            $table->dropColumn('statuses');
        });
    }
};
