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
        $now = now();
        DB::table('branches')->orderBy('id')->get(['id', 'sales_shift_count'])->each(
            function (object $branch) use ($now): void {
                $count = max(1, (int) $branch->sales_shift_count);
                $windows = $count === 1
                    ? [['07:00:00', '23:00:00']]
                    : [['07:00:00', '15:00:00'], ['15:00:00', '23:00:00'], ['23:00:00', '07:00:00']];

                foreach (range(1, $count) as $number) {
                    [$start, $end] = $windows[$number - 1] ?? ['00:00:00', '23:59:59'];
                    DB::table('branch_sales_shifts')->insert([
                        'branch_id' => $branch->id,
                        'shift_number' => $number,
                        'name' => 'Shift '.$number,
                        'start_time' => $start,
                        'end_time' => $end,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            },
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally retained because these rows may have been edited after deployment.
    }
};
