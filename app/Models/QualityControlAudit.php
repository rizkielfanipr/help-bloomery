<?php

namespace App\Models;

use Database\Factories\QualityControlAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QualityControlAudit extends Model
{
    /** @use HasFactory<QualityControlAuditFactory> */
    use HasFactory;

    protected $fillable = [
        'audit_number', 'branch_id', 'auditor_id', 'audit_date', 'audit_type',
        'store_leader_name', 'store_leader_present', 'status', 'score',
        'earned_points', 'maximum_points', 'rating', 'top_findings',
        'corrective_action_required', 'overall_notes', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'audit_date' => 'date',
            'store_leader_present' => 'boolean',
            'score' => 'float',
            'earned_points' => 'integer',
            'maximum_points' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $audit): void {
            $audit->audit_number ??= 'QC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
            $audit->auditor_id ??= auth()->id();
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QualityControlAuditItem::class)->orderBy('sort_order');
    }

    public function recalculateScore(): void
    {
        $items = $this->items()->whereNotNull('result')->where('result', '!=', 'not_applicable')->get();
        $maximumPoints = $items->sum('maximum_points');
        $earnedPoints = $items->sum('earned_points');
        $score = $maximumPoints > 0 ? round(($earnedPoints / $maximumPoints) * 100, 2) : 0;

        $this->forceFill([
            'maximum_points' => $maximumPoints,
            'earned_points' => $earnedPoints,
            'score' => $score,
            'rating' => $maximumPoints > 0 ? match (true) {
                $score >= 85 => 'green',
                $score >= 65 => 'yellow',
                default => 'red',
            } : null,
        ])->saveQuietly();
    }
}
