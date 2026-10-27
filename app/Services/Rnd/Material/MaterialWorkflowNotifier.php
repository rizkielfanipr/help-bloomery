<?php

namespace App\Services\Rnd\Material;

use App\Filament\Helpdesk\Pages\ViewProjectProductPage;
use App\Filament\Helpdesk\Resources\MaterialPricelists\MaterialPricelistResource;
use App\Filament\Helpdesk\Resources\MaterialReviews\MaterialReviewResource;
use App\Filament\Helpdesk\Resources\MaterialSourcings\MaterialSourcingResource;
use App\Filament\Helpdesk\Resources\ReviewSourcings\ReviewSourcingResource;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Notifications\MaterialWorkflowNotification;
use App\Services\Notifications\NotificationDeliveryRecorder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Internal notifications for the Master Bahan workflow (§24) plus the Telegram review event
 * (§14). Notifications are sent after the surrounding transaction commits, so a rollback sends
 * nothing; a notification failure never breaks the workflow step.
 */
class MaterialWorkflowNotifier
{
    /** Who acts on each event, by permission. */
    private const AUDIENCE = [
        'review_submitted' => ['review rnd materials as it'],
        'revision_required' => [],
        'esb_synced' => ['review rnd materials as it'],
        'esb_failed' => ['review rnd materials as it', 'reconcile rnd material esb'],
        'sourcing_requested' => ['submit material sourcing'],
        'sample_handed_over' => ['receive material samples', 'review material samples as rnd'],
        'resample_requested' => ['submit material sourcing', 'hand over material samples'],
        'pricing_ready' => ['manage material pricelist'],
        'pricing_returned' => ['review rnd materials as it', 'reconcile rnd material esb'],
        'completed' => [],
    ];

    public function __construct(private readonly NotificationDeliveryRecorder $deliveries) {}

    /**
     * @param  bool  $includeCreator  also notify the R&D owner of the material
     */
    public function notify(RndProductEsbMaterial $material, string $event, string $title, string $message, bool $includeCreator = false): void
    {
        DB::afterCommit(function () use ($material, $event, $title, $message, $includeCreator): void {
            try {
                // Each recipient is linked to the work queue they act in; the R&D owner who is only
                // informed is linked to the material on its product page.
                $actors = $this->recipients(self::AUDIENCE[$event] ?? [], null);
                if ($actors->isNotEmpty()) {
                    Notification::send($actors, new MaterialWorkflowNotification($material, $event, $title, $message, $this->queueUrl($material, $event)));
                }

                $owner = $includeCreator ? $this->recipients([], $material->created_by)->reject(fn (User $user): bool => $actors->contains('id', $user->id)) : collect();
                if ($owner->isNotEmpty()) {
                    Notification::send($owner, new MaterialWorkflowNotification($material, $event, $title, $message, $this->productUrl($material)));
                }
            } catch (Throwable $exception) {
                Log::warning('Material workflow notification failed.', ['material' => $material->id, 'event' => $event, 'exception' => $exception::class]);
            }
        });
    }

    /**
     * One Telegram message per material + submission version (§14.1). Must be called inside the
     * submit transaction so a rollback leaves neither the delivery row nor the queued job.
     */
    public function telegramReviewSubmitted(RndProductEsbMaterial $material, RndMaterialReview $review, User $submitter): void
    {
        $units = $material->units()->get()
            ->map(fn ($unit): string => $unit->is_base ? "{$unit->uom_name} (base)" : $unit->uom_name.' ×'.rtrim(rtrim(number_format((float) $unit->conversion_factor, 4, '.', ''), '0'), '.'))
            ->implode(', ');
        $title = $material->isWip() ? 'Review Master WIP Baru' : 'Review Master Bahan Baru';
        $message = implode("\n", [
            $title,
            'Nama: '.$material->product_name,
            'Kode: '.$material->product_code,
            'Jenis: '.MaterialClassification::label($material),
            'UOM: '.($units ?: '-'),
            'Pengaju: '.$submitter->name,
            'Waktu: '.$review->submitted_at->copy()->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB',
            'Versi: '.$review->submission_version,
            'Detail: '.($this->reviewUrl($material) ?? '-'),
        ]);

        $this->deliveries->telegram(
            'material_review_submitted',
            $material,
            'it_review',
            "telegram:material_review_submitted:material:{$material->id}:v{$review->submission_version}:it_review",
            $message,
        );
    }

    /**
     * The work queue of the audience that acts on the event.
     */
    public function queueUrl(RndProductEsbMaterial $material, string $event): ?string
    {
        try {
            return match ($event) {
                'sourcing_requested', 'resample_requested' => MaterialSourcingResource::getUrl('index', panel: 'helpdesk'),
                'sample_handed_over' => ReviewSourcingResource::getUrl('index', panel: 'helpdesk'),
                'pricing_ready' => MaterialPricelistResource::getUrl('index', panel: 'helpdesk'),
                'revision_required', 'completed' => $this->productUrl($material),
                default => $this->reviewUrl($material),
            };
        } catch (Throwable) {
            return null;
        }
    }

    public function productUrl(RndProductEsbMaterial $material): ?string
    {
        try {
            return $material->product
                ? ViewProjectProductPage::getUrl(['project' => $material->product->rnd_project_id, 'product' => $material->rnd_project_product_id], panel: 'helpdesk')
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function reviewUrl(RndProductEsbMaterial $material): ?string
    {
        try {
            return MaterialReviewResource::getUrl('view', ['record' => $material], panel: 'helpdesk');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $permissions
     * @return Collection<int, User>
     */
    private function recipients(array $permissions, ?int $creatorId): Collection
    {
        $users = collect();
        foreach ($permissions as $permission) {
            try {
                $users = $users->merge(User::permission($permission)->where('is_active', true)->get());
            } catch (Throwable) {
                continue;
            }
        }

        if ($creatorId) {
            $users = $users->merge(User::query()->whereKey($creatorId)->where('is_active', true)->get());
        }

        return $users->unique('id')->values();
    }
}
