<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> table => code prefix */
    private array $tables = [
        'design_requests' => 'DS-',
        'purchase_requests' => 'PR-',
        'service_requests' => 'SR-',
        'content_requests' => 'CR-',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $prefix) {
            if (! DB::getSchemaBuilder()->hasColumn($table, 'code')) {
                continue;
            }

            $ids = DB::table($table)->whereNull('code')->orderBy('id')->pluck('id');

            foreach ($ids as $id) {
                do {
                    $code = $prefix.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                } while (DB::table($table)->where('code', $code)->exists());

                DB::table($table)->where('id', $id)->update(['code' => $code]);
            }
        }
    }

    public function down(): void
    {
        // Backfilled codes are additive data; nothing to reverse.
    }
};
