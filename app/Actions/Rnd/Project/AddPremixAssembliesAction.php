<?php

namespace App\Actions\Rnd\Project;

use App\Enums\RndProjectType;
use App\Models\RndProject;
use App\Models\User;
use App\Services\Rnd\Project\PremixAssemblyCatalogQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AddPremixAssembliesAction
{
    public function __construct(private readonly PremixAssemblyCatalogQuery $catalog) {}

    /** @param list<int> $catalogIds */
    public function execute(User $actor, RndProject $project, array $catalogIds): int
    {
        Gate::forUser($actor)->authorize('edit rnd projects');
        abort_unless($project->project_type === RndProjectType::Premix, 422);

        $ids = collect($catalogIds)->map(fn ($id): int => (int) $id)->filter()->unique()->take(100)->values();
        $rows = $this->catalog->selectable()->whereKey($ids)->get();

        if ($ids->isEmpty() || $rows->count() !== $ids->count()) {
            throw ValidationException::withMessages(['selectedAssemblyIds' => 'Satu atau beberapa BOM tidak tersedia atau bukan Assembly.']);
        }

        return DB::transaction(function () use ($actor, $project, $rows): int {
            $before = $project->premixAssemblies()->count();
            $timestamp = now();

            $project->premixAssemblies()->insertOrIgnore($rows->map(fn ($catalog): array => [
                'rnd_project_id' => $project->id,
                'esb_bom_id' => $catalog->esb_bom_id,
                'bom_code' => $catalog->bom_code,
                'bom_name' => $catalog->bom_name,
                'product_name' => $catalog->product_name,
                'uom_name' => $catalog->uom_name,
                'bom_type_name' => $catalog->bom_type_name,
                'bom_type_id' => $catalog->bom_type_id,
                'catalog_is_active' => $catalog->is_active,
                'detail_snapshot' => json_encode($catalog->detail_snapshot, JSON_THROW_ON_ERROR),
                'created_by' => $actor->id,
                'catalog_synced_at' => $catalog->last_synced_at,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all());

            return $project->premixAssemblies()->count() - $before;
        });
    }
}
