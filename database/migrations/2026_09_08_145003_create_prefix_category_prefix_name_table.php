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
        // MySQL may leave this table behind when a previous CREATE TABLE
        // attempt fails while adding an index.
        Schema::dropIfExists('prefix_category_prefix_name');

        Schema::create('prefix_category_prefix_name', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prefix_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prefix_name_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['prefix_category_id', 'prefix_name_id'], 'prefix_category_name_unique');
        });

        $now = now();
        $prefixCategoryIds = DB::table('prefix_categories')->pluck('id');
        $prefixNameIds = DB::table('prefix_names')->pluck('id');

        $existingAssignments = $prefixNameIds->crossJoin($prefixCategoryIds)
            ->map(fn (array $ids): array => [
                'prefix_name_id' => $ids[0],
                'prefix_category_id' => $ids[1],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        if ($existingAssignments !== []) {
            DB::table('prefix_category_prefix_name')->insert($existingAssignments);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prefix_category_prefix_name');
    }
};
