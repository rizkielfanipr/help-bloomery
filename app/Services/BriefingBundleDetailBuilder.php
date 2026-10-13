<?php

namespace App\Services;

use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;
use App\Models\BriefingRecordTransition;
use Illuminate\Support\Facades\Storage;

/**
 * docs/daily-briefing-bundle-approval-prd.md §13/§18: everything the Monitoring review modal shows,
 * resolved in PHP with one eager load so the Blade view never queries. Point state uses the same
 * completeness rule as submission and review.
 */
class BriefingBundleDetailBuilder
{
    private const PHOTO_URL_TTL_MINUTES = 60;

    public function __construct(private readonly BriefingBundleCompletenessService $completeness) {}

    /**
     * @return array{
     *     bundle: BriefingRecord,
     *     completeness: BriefingBundleCompleteness,
     *     contributors: list<string>,
     *     points: list<array{key: string, label: string, type: string, required: bool, state: string, notes: ?string, photoUrls: list<string>, submittedBy: ?string, submittedAt: ?string, correctionNote: ?string}>,
     *     history: list<array{from: ?string, to: string, actor: ?string, reason: ?string, at: string}>,
     * }
     */
    public function build(BriefingRecord $bundle): array
    {
        $bundle->loadMissing(['branch:id,name', 'reviewer:id,name', 'requirements', 'items.submitter:id,name', 'transitions.actor:id,name']);
        $itemsByKey = $bundle->items->keyBy('task_key');

        return [
            'bundle' => $bundle,
            'completeness' => $this->completeness->evaluate($bundle),
            'contributors' => $bundle->items
                ->map(fn (BriefingItem $item): ?string => $item->submitter?->name)
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'points' => $bundle->requirements
                ->sortBy('sort_order')
                ->map(fn (BriefingRecordRequirement $requirement): array => $this->point($requirement, $itemsByKey->get($requirement->task_key)))
                ->values()
                ->all(),
            'history' => $bundle->transitions
                ->sortBy('id')
                ->map(fn (BriefingRecordTransition $transition): array => [
                    'from' => $transition->from_status?->getLabel(),
                    'to' => $transition->to_status->getLabel(),
                    'actor' => $transition->actor?->name,
                    'reason' => $transition->reason,
                    'at' => $transition->created_at->format('d M Y H:i'),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{key: string, label: string, type: string, required: bool, state: string, notes: ?string, photoUrls: list<string>, submittedBy: ?string, submittedAt: ?string, correctionNote: ?string}
     */
    private function point(BriefingRecordRequirement $requirement, ?BriefingItem $item): array
    {
        $state = match (true) {
            (bool) $item?->requires_correction => 'correction',
            $this->completeness->itemSatisfies($requirement->submission_type, $item) => 'complete',
            default => 'missing',
        };
        $submitted = $item?->hasSubmittedContent() ?? false;

        return [
            'key' => $requirement->task_key,
            'label' => $requirement->label,
            'type' => $requirement->submission_type->getLabel(),
            'required' => $requirement->is_required,
            'state' => $state,
            'notes' => $item?->notes,
            'photoUrls' => collect($item?->photo_paths ?? [])
                ->map(fn (string $path): string => Storage::disk('b2')->temporaryUrl($path, now()->addMinutes(self::PHOTO_URL_TTL_MINUTES)))
                ->values()
                ->all(),
            'submittedBy' => $submitted ? $item->submitter?->name : null,
            'submittedAt' => $submitted ? ($item->submitted_at?->format('d M Y H:i') ?? $item->completed_at?->format('d M Y H:i')) : null,
            'correctionNote' => $item?->correction_note,
        ];
    }
}
