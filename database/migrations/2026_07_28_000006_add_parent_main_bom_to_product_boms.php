<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_project_product_boms', function (Blueprint $table): void {
            $table->foreignId('parent_rnd_project_bom_id')
                ->nullable()
                ->after('rnd_project_bom_id')
                ->constrained('rnd_project_boms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rnd_project_product_boms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_rnd_project_bom_id');
        });
    }
};
