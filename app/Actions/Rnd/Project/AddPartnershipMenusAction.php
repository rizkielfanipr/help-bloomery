<?php

namespace App\Actions\Rnd\Project;

use App\Enums\RndProjectType;
use App\Models\RndProject;
use App\Models\User;
use App\Services\Rnd\Project\PartnershipMenuCatalogQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AddPartnershipMenusAction
{
    public function __construct(private readonly PartnershipMenuCatalogQuery $catalog) {}

    /** @param list<int> $catalogIds */
    public function execute(User $actor, RndProject $project, array $catalogIds): int
    {
        Gate::forUser($actor)->authorize('edit rnd projects');
        abort_unless($project->project_type === RndProjectType::Partnership, 422);

        $ids = collect($catalogIds)->map(fn ($id): int => (int) $id)->filter()->unique()->take(100)->values();
        $rows = $this->catalog->selectable()->whereKey($ids)->get();

        if ($ids->isEmpty() || $rows->count() !== $ids->count()) {
            throw ValidationException::withMessages(['selectedPartnershipMenuIds' => 'Satu atau beberapa Menu tidak tersedia pada katalog aktif.']);
        }

        return DB::transaction(function () use ($actor, $project, $rows): int {
            $before = $project->partnershipMenus()->count();
            $timestamp = now();
            $nextOrder = ((int) $project->partnershipMenus()->max('sort_order')) + 1;

            $project->partnershipMenus()->insertOrIgnore($rows->values()->map(fn ($catalog, int $index): array => [
                'rnd_project_id' => $project->id,
                'company_code' => $catalog->company_code,
                'branch_code' => $catalog->branch_code,
                'esb_menu_id' => $catalog->menu_id,
                'menu_code' => $catalog->menu_code,
                'menu_name' => $catalog->menu_name,
                'category_name' => $catalog->category_detail,
                'menu_snapshot' => json_encode([
                    'bom_id' => $catalog->bom_id,
                    'bom_name' => $catalog->bom_name,
                    'synced_at' => $catalog->synced_at?->toIso8601String(),
                ], JSON_THROW_ON_ERROR),
                'sort_order' => $nextOrder + $index,
                'created_by' => $actor->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all());

            return $project->partnershipMenus()->count() - $before;
        });
    }
}
