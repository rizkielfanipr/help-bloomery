<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_method_group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_method_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('esb_payment_method_id');
            $table->string('esb_payment_method_name');
            $table->timestamps();
            $table->unique(['payment_method_group_id', 'esb_payment_method_id'], 'pmgi_group_method_unique');
        });

        Schema::create('esb_payment_method_cache', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('esb_payment_method_id');
            $table->string('esb_payment_method_name');
            $table->string('esb_payment_method_code')->nullable();
            $table->unsignedInteger('esb_payment_method_type_id')->nullable();
            $table->string('esb_payment_method_type_name')->nullable();
            $table->string('branch_code', 50);
            $table->string('branch_name');
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['esb_payment_method_id', 'branch_code'], 'epmc_method_branch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esb_payment_method_cache');
        Schema::dropIfExists('payment_method_group_items');
        Schema::dropIfExists('payment_method_groups');
    }
};
