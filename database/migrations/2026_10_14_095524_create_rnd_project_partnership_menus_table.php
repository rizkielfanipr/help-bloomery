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
        Schema::create('rnd_project_partnership_menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_id')->constrained()->cascadeOnDelete();
            $table->string('company_code', 20);
            $table->string('branch_code', 50);
            $table->unsignedBigInteger('esb_menu_id');
            $table->string('menu_code')->nullable();
            $table->string('menu_name');
            $table->string('category_name')->nullable();
            $table->json('menu_snapshot')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rnd_project_id', 'company_code', 'branch_code', 'esb_menu_id'], 'rnd_project_partner_menu_unique');
            $table->index(['rnd_project_id', 'sort_order'], 'rnd_project_partner_menu_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rnd_project_partnership_menus');
    }
};
