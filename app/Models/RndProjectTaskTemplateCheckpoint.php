<?php

namespace App\Models;

use App\Enums\RndProjectTaskCategory;
use App\Enums\RndProjectTaskPriority;
use Database\Factories\RndProjectTaskTemplateCheckpointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * One checkpoint of a Template — becomes exactly one `RndProjectTask` when applied
 * (docs/rnd-project-checkpoint-calendar-prd.md §16.2). It holds no dates: the assign date and
 * deadline are set per Project when the template is applied. It may suggest default Branches and
 * PICs, which are re-validated on apply, but never stores runtime state (§12.8).
 */
class RndProjectTaskTemplateCheckpoint extends Model
{
    /** @use HasFactory<RndProjectTaskTemplateCheckpointFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_project_task_template_id',
        'title',
        'task_type',
        'description',
        'priority',
        'sort_order',
    ];

    protected $attributes = [
        'priority' => 'medium',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'task_type' => RndProjectTaskCategory::class,
            'priority' => RndProjectTaskPriority::class,
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RndProjectTaskTemplate::class, 'rnd_project_task_template_id');
    }

    /**
     * Default Branches pre-filled when the template is applied; they can still be changed per
     * Project.
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'rnd_project_task_template_checkpoint_branches', 'rnd_project_task_template_checkpoint_id');
    }

    /**
     * Default PICs, each tied to one of the checkpoint's Branches through the `branch_id` pivot.
     */
    public function picUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'rnd_project_task_template_checkpoint_pics', 'rnd_project_task_template_checkpoint_id')
            ->withPivot('branch_id');
    }

    /**
     * Default Branch/PIC mapping in the shape of the Branch & PIC picker. Expects `branches` and
     * `picUsers` to be eager-loaded when called in a loop.
     *
     * @return list<array{branch_id: string, user_ids: list<string>}>
     */
    public function branchPicRows(): array
    {
        return $this->branches
            ->map(fn (Branch $branch): array => [
                'branch_id' => (string) $branch->id,
                'user_ids' => $this->picUsers
                    ->filter(fn (User $user): bool => (int) $user->pivot->branch_id === $branch->id)
                    ->map(fn (User $user): string => (string) $user->id)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Replaces the default Branches and PICs with the given picker rows.
     *
     * @param  array<int, array{branch_id: int|string|null, user_ids?: array<int, int|string>}>  $rows
     */
    public function syncBranchPics(array $rows): void
    {
        $rows = collect($rows)->filter(fn (array $row): bool => filled($row['branch_id'] ?? null));

        DB::transaction(function () use ($rows): void {
            $this->branches()->sync($rows->pluck('branch_id')->map(fn ($branchId): int => (int) $branchId)->unique()->values()->all());
            $this->picUsers()->detach();

            foreach ($rows as $row) {
                foreach (array_unique($row['user_ids'] ?? []) as $userId) {
                    $this->picUsers()->attach((int) $userId, ['branch_id' => (int) $row['branch_id']]);
                }
            }
        });

        $this->unsetRelation('branches')->unsetRelation('picUsers');
    }
}
