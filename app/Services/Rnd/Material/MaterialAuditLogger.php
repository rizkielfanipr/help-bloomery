<?php

namespace App\Services\Rnd\Material;

use App\Models\RndMaterialAuditLog;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Monitoring\MonitoringContext;
use App\Services\Monitoring\MonitoringSanitizer;

/**
 * Writes the append-only Master Bahan audit trail (§27): who, when, which action, status
 * before/after, and reason. Field values pass the monitoring sanitizer first.
 */
class MaterialAuditLogger
{
    public function __construct(private readonly MonitoringSanitizer $sanitizer) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function event(
        RndProductEsbMaterial $material,
        string $event,
        ?User $actor,
        ?string $reason = null,
        ?string $from = null,
        ?string $to = null,
        array $metadata = [],
        ?RndMaterialReview $review = null,
    ): RndMaterialAuditLog {
        return RndMaterialAuditLog::query()->create([
            'rnd_product_esb_material_id' => $material->id,
            'rnd_material_review_id' => $review?->id,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'reason' => $reason !== null ? $this->sanitizer->message($reason, 2000) : null,
            'metadata' => ($metadata + ['correlation_id' => MonitoringContext::correlationId()]),
        ]);
    }

    public function fieldChange(RndProductEsbMaterial $material, string $path, mixed $before, mixed $after, User $actor, string $reason, ?RndMaterialReview $review): RndMaterialAuditLog
    {
        return RndMaterialAuditLog::query()->create([
            'rnd_product_esb_material_id' => $material->id,
            'rnd_material_review_id' => $review?->id,
            'event' => 'technical_amendment',
            'field_path' => mb_substr($path, 0, 120),
            'before_value' => ['v' => $this->sanitizer->sanitize($before)],
            'after_value' => ['v' => $this->sanitizer->sanitize($after)],
            'actor_id' => $actor->id,
            'reason' => $this->sanitizer->message($reason, 2000),
        ]);
    }
}
