<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/rnd-internal-memo-brand-prd.md §13.2, §26.2. Active-Memo uniqueness moves from
 * (company_code, period, revision) to (brand_id, period, revision) so different Brands can share a
 * period. `period_month_if_active` keeps the existing soft-delete semantics (NULL once deleted).
 * Legacy rows with `brand_id = NULL` never collide; the application still requires a Brand.
 *
 * Both directions audit collisions first and refuse instead of failing halfway. Rolling back is
 * only possible while no two active Memos share a company/period/revision; once different Brands
 * use the same period, the supported path is an application rollback or a forward-fix.
 */
return new class extends Migration
{
    private const OLD_INDEX = 'rnd_internal_memos_active_period_unique';

    private const NEW_INDEX = 'rnd_memos_brand_active_period_unique';

    public function up(): void
    {
        $this->guardAgainstCollisions(['brand_id', 'period_month_if_active', 'revision'], 'brand_id');

        if (! Schema::hasIndex('rnd_internal_memos', self::NEW_INDEX)) {
            Schema::table('rnd_internal_memos', function (Blueprint $table): void {
                $table->unique(['brand_id', 'period_month_if_active', 'revision'], self::NEW_INDEX);
            });
        }

        if (Schema::hasIndex('rnd_internal_memos', self::OLD_INDEX)) {
            Schema::table('rnd_internal_memos', function (Blueprint $table): void {
                $table->dropUnique(self::OLD_INDEX);
            });
        }
    }

    public function down(): void
    {
        $this->guardAgainstCollisions(['company_code', 'period_month_if_active', 'revision'], 'company_code');

        if (! Schema::hasIndex('rnd_internal_memos', self::OLD_INDEX)) {
            Schema::table('rnd_internal_memos', function (Blueprint $table): void {
                $table->unique(['company_code', 'period_month_if_active', 'revision'], self::OLD_INDEX);
            });
        }

        if (Schema::hasIndex('rnd_internal_memos', self::NEW_INDEX)) {
            Schema::table('rnd_internal_memos', function (Blueprint $table): void {
                $table->dropUnique(self::NEW_INDEX);
            });
        }
    }

    /** @param list<string> $columns */
    private function guardAgainstCollisions(array $columns, string $notNullColumn): void
    {
        $collisions = DB::table('rnd_internal_memos')
            ->select($columns)
            ->selectRaw('COUNT(*) as aggregate')
            ->whereNull('deleted_at')
            ->whereNotNull($notNullColumn)
            ->groupBy($columns)
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($collisions > 0) {
            throw new RuntimeException(sprintf(
                'Tidak dapat memasang unique index Memo Internal pada (%s): %d kombinasi aktif bertabrakan. Selesaikan datanya terlebih dahulu; jangan memaksa migration ini.',
                implode(', ', $columns),
                $collisions,
            ));
        }
    }
};
