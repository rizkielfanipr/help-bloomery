<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_request_repairs', function (Blueprint $table): void {
            $table->json('before_photos')->nullable()->after('before_photo');
            $table->json('after_photos')->nullable()->after('after_photo');
        });

        DB::table('service_request_repairs')
            ->select(['id', 'before_photo', 'after_photo'])
            ->orderBy('id')
            ->chunkById(100, function ($repairs): void {
                foreach ($repairs as $repair) {
                    DB::table('service_request_repairs')
                        ->where('id', $repair->id)
                        ->update([
                            'before_photos' => $repair->before_photo ? json_encode([$repair->before_photo]) : null,
                            'after_photos' => $repair->after_photo ? json_encode([$repair->after_photo]) : null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('service_request_repairs', function (Blueprint $table): void {
            $table->dropColumn(['before_photos', 'after_photos']);
        });
    }
};
