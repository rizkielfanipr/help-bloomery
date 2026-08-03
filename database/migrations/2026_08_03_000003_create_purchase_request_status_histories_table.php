<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::table('purchase_requests')
            ->orderBy('id')
            ->chunkById(500, function ($requests): void {
                $rows = [];

                foreach ($requests as $request) {
                    $rows[] = [
                        'purchase_request_id' => $request->id,
                        'from_status' => null,
                        'to_status' => $request->status,
                        'changed_by' => $request->processed_by ?: $request->user_id,
                        'notes' => $request->status === 'rejected' ? $request->admin_notes : null,
                        'created_at' => $request->updated_at ?: $request->created_at,
                        'updated_at' => $request->updated_at ?: $request->created_at,
                    ];
                }

                if ($rows !== []) {
                    DB::table('purchase_request_status_histories')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_status_histories');
    }
};
