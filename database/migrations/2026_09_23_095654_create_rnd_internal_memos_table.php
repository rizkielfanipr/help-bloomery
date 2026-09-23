<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `memo_number` is a free-text business document number typed by the R&D Operator
     * (docs/rnd-internal-memo-prd.md §7.1) and is globally unique. `revision` numbers the
     * corrections of a single monthly memo; the (company_code, period_month, revision)
     * combination is unique so a period cannot get two documents at the same revision.
     * A revision's predecessor is looked up by that combination — there is no separate
     * "revised from" column because the PRD schema (§12.1) does not declare one.
     */
    public function up(): void
    {
        Schema::create('rnd_internal_memos', function (Blueprint $table): void {
            $table->id();
            $table->string('company_code', 10)->default('BLSS');
            $table->string('memo_number')->unique();
            $table->string('title');
            $table->date('period_month');
            $table->date('memo_date');
            $table->string('recipient');
            $table->string('sender');
            $table->string('subject');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('source_synced_at')->nullable();
            $table->string('snapshot_hash')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_code', 'period_month', 'revision']);
            $table->index(['company_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_internal_memos');
    }
};
