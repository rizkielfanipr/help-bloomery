<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/daily-briefing-bundle-approval-prd.md §7.2: the obligations a bundle was created with,
     * snapshotted apart from briefing_items so a required point never has to exist as an empty
     * submission row (which is how synthetic rejections used to creep in).
     */
    public function up(): void
    {
        Schema::create('briefing_record_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('briefing_record_id')->constrained()->cascadeOnDelete();
            $table->string('task_key');
            $table->string('label');
            $table->string('submission_type');
            $table->boolean('is_required')->default(true);
            $table->boolean('include_in_score')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['briefing_record_id', 'task_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('briefing_record_requirements');
    }
};
