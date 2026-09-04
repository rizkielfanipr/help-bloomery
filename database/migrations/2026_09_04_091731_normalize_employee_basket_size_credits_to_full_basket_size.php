<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('basket_size_records')->orderBy('id')->chunkById(100, function ($records): void {
            foreach ($records as $record) {
                DB::table('basket_size_employee_records')
                    ->where('basket_size_record_id', $record->id)
                    ->update(['basket_size_credit' => $record->basket_size]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('basket_size_records')->orderBy('id')->chunkById(100, function ($records): void {
            foreach ($records as $record) {
                $credit = $record->basket_size !== null && $record->staff_count > 0
                    ? $record->basket_size / $record->staff_count
                    : null;

                DB::table('basket_size_employee_records')
                    ->where('basket_size_record_id', $record->id)
                    ->update(['basket_size_credit' => $credit]);
            }
        });
    }
};
