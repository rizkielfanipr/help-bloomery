<?php

namespace App\Models;

use Database\Factories\RndProjectTaskTemplateApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Audit boundary of one "Gunakan Template" batch (docs/rnd-project-checkpoint-calendar-prd.md
 * §16.3). `idempotency_key` is unique so a retried or double-submitted apply never creates a
 * second batch; `template_name` and `checkpoint_snapshot` keep the history readable even after
 * the template is edited or removed.
 */
class RndProjectTaskTemplateApplication extends Model
{
    /** @use HasFactory<RndProjectTaskTemplateApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_project_id',
        'rnd_project_task_template_id',
        'template_name',
        'idempotency_key',
        'checkpoint_snapshot',
        'applied_by',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'checkpoint_snapshot' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'rnd_project_id')->withTrashed();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RndProjectTaskTemplate::class, 'rnd_project_task_template_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(RndProjectTask::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
