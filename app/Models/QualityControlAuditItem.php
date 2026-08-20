<?php

namespace App\Models;

use Database\Factories\QualityControlAuditItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityControlAuditItem extends Model
{
    /** @use HasFactory<QualityControlAuditItemFactory> */
    use HasFactory;

    protected $fillable = [
        'quality_control_audit_id', 'quality_control_checklist_item_id',
        'section_code', 'section_name', 'question', 'check_procedure',
        'maximum_points', 'earned_points', 'is_critical', 'requires_photo',
        'result', 'notes', 'evidence_photos', 'corrective_action',
        'action_pic_id', 'action_due_date', 'action_status',
        'action_evidence_photos', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'maximum_points' => 'integer',
            'earned_points' => 'integer',
            'is_critical' => 'boolean',
            'requires_photo' => 'boolean',
            'evidence_photos' => 'array',
            'action_due_date' => 'date',
            'action_evidence_photos' => 'array',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->earned_points = $item->result === 'pass' ? $item->maximum_points : 0;
        });

        static::saved(fn (self $item) => $item->audit?->recalculateScore());
        static::deleted(fn (self $item) => $item->audit?->recalculateScore());
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(QualityControlAudit::class, 'quality_control_audit_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(QualityControlChecklistItem::class, 'quality_control_checklist_item_id');
    }

    public function actionPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_pic_id');
    }
}
