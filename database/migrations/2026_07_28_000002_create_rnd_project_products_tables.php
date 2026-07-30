<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_project_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('product_code', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('offline_price', 15, 2)->default(0);
            $table->decimal('online_price', 15, 2)->default(0);
            $table->date('release_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rnd_project_id', 'status']);
            $table->unique(['rnd_project_id', 'product_code']);
        });

        Schema::create('rnd_project_product_boms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rnd_project_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rnd_project_bom_id')->constrained()->cascadeOnDelete();
            $table->string('usage_type', 30)->default('main');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['rnd_project_product_id', 'rnd_project_bom_id'], 'rnd_product_bom_unique');
        });

        $now = now();
        $projectIds = DB::table('rnd_project_boms')->distinct()->pluck('rnd_project_id');

        foreach ($projectIds as $projectId) {
            $productId = DB::table('rnd_project_products')->insertGetId([
                'rnd_project_id' => $projectId,
                'name' => 'Unassigned Product',
                'description' => 'Produk sementara untuk BOM yang dibuat sebelum fitur Product Release tersedia.',
                'offline_price' => 0,
                'online_price' => 0,
                'status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $bomIds = DB::table('rnd_project_boms')->where('rnd_project_id', $projectId)->pluck('id');
            foreach ($bomIds as $bomId) {
                DB::table('rnd_project_product_boms')->insert([
                    'rnd_project_product_id' => $productId,
                    'rnd_project_bom_id' => $bomId,
                    'usage_type' => 'main',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_project_product_boms');
        Schema::dropIfExists('rnd_project_products');
    }
};
