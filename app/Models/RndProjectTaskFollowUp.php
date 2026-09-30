<?php

namespace App\Models;

use App\Enums\RndProjectTaskFollowUpType;
use Database\Factories\RndProjectTaskFollowUpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One append-only progress/submission entry from a PIC (docs/rnd-project-task-calendar-prd.md
 * §8.4, §12). Never updated or deleted — corrections are new rows so history is preserved
 * (business rule #13).
 */
class RndProjectTaskFollowUp extends Model
{
    /** @use HasFactory<RndProjectTaskFollowUpFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_project_task_assignment_id',
        'submitted_by',
        'follow_up_type',
        'notes',
        'estimated_completion_date',
        'result_attachments',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_type' => RndProjectTaskFollowUpType::class,
            'estimated_completion_date' => 'date',
            'result_attachments' => 'array',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(RndProjectTaskAssignment::class, 'rnd_project_task_assignment_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
