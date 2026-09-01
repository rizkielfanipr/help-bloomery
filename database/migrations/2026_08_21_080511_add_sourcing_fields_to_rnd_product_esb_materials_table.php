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
        Schema::table('rnd_product_esb_materials', function (Blueprint $table) {
            $table->string('sourcing_status')->default('not_started')->after('status');
            $table->foreignId('sourcing_selected_id')->nullable()
                ->constrained('material_sourcings', indexName: 'rnd_esb_materials_sourcing_selected_id_foreign')
                ->nullOnDelete();
            $table->foreignId('rnd_reviewed_by')->nullable()
                ->constrained('users', indexName: 'rnd_esb_materials_rnd_reviewed_by_foreign')
                ->nullOnDelete();
            $table->timestamp('rnd_reviewed_at')->nullable();
            $table->text('rnd_note')->nullable();
            $table->foreignId('finance_reviewed_by')->nullable()
                ->constrained('users', indexName: 'rnd_esb_materials_finance_reviewed_by_foreign')
                ->nullOnDelete();
            $table->timestamp('finance_reviewed_at')->nullable();
            $table->text('finance_note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rnd_product_esb_materials', function (Blueprint $table) {
            $table->dropForeign('rnd_esb_materials_sourcing_selected_id_foreign');
            $table->dropForeign('rnd_esb_materials_rnd_reviewed_by_foreign');
            $table->dropForeign('rnd_esb_materials_finance_reviewed_by_foreign');
            $table->dropColumn([
                'sourcing_status',
                'sourcing_selected_id',
                'rnd_reviewed_by',
                'rnd_reviewed_at',
                'rnd_note',
                'finance_reviewed_by',
                'finance_reviewed_at',
                'finance_note',
            ]);
        });
    }
};
