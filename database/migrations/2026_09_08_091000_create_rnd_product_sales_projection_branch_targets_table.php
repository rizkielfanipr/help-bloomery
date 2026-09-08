<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rnd_product_sales_projection_branch_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rnd_product_sales_projection_id');
            $table->foreignId('branch_id');
            $table->decimal('target_quantity', 15, 2);
            $table->timestamps();

            $table->foreign('rnd_product_sales_projection_id', 'rnd_projection_branch_projection_fk')
                ->references('id')->on('rnd_product_sales_projections')->cascadeOnDelete();
            $table->foreign('branch_id', 'rnd_projection_branch_branch_fk')
                ->references('id')->on('branches')->cascadeOnDelete();
            $table->unique(
                ['rnd_product_sales_projection_id', 'branch_id'],
                'rnd_projection_branch_target_unique',
            );
        });

        DB::table('rnd_project_products')->where('storage_condition', 'ambient')->update(['storage_condition' => 'dry']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('rnd_project_products')->where('storage_condition', 'dry')->update(['storage_condition' => 'ambient']);
        Schema::dropIfExists('rnd_product_sales_projection_branch_targets');
    }
};
