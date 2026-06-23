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
        $hasIndex = collect(Schema::getIndexes('casual_position_registrations'))
            ->contains(fn ($index) => $index['name'] === 'casual_position_registrations_user_id_unique');

        if ($hasIndex) {
            Schema::table('casual_position_registrations', function (Blueprint $table) {
                $table->dropForeign('casual_position_registrations_user_id_foreign');
                $table->dropUnique('casual_position_registrations_user_id_unique');
                $table->index('user_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::table('casual_position_registrations', function (Blueprint $table) {
            $table->dropForeign('casual_position_registrations_user_id_foreign');
            $table->dropIndex(['user_id']);
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
