<?php

namespace App\Models;

use App\Enums\BriefingSubmissionType;
use Database\Factories\BriefingRecordRequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/daily-briefing-bundle-approval-prd.md §7.2: one obligation a bundle was created with,
 * copied from the task master so later edits to the task never rewrite history.
 */
class BriefingRecordRequirement extends Model
{
    /** @use HasFactory<BriefingRecordRequirementFactory> */
    use HasFactory;

    protected $fillable = [
        'briefing_record_id',
        'task_key',
        'label',
        'submission_type',
        'is_required',
        'include_in_score',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'submission_type' => BriefingSubmissionType::class,
            'is_required' => 'boolean',
            'include_in_score' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(BriefingRecord::class, 'briefing_record_id');
    }

    /**
     * Every assigned task is required in the current release; `is_required` is still snapshotted
     * so an optional-point decision later does not need a schema change.
     *
     * @return array{task_key: string, label: string, submission_type: string, is_required: bool, include_in_score: bool, sort_order: int}
     */
    public static function attributesFromTask(BriefingTask $task): array
    {
        return [
            'task_key' => $task->key,
            'label' => $task->label,
            'submission_type' => $task->submission_type->value,
            'is_required' => true,
            'include_in_score' => (bool) $task->include_in_score,
            'sort_order' => (int) $task->sort_order,
        ];
    }
}
