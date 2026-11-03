<?php

namespace App\Models;

use App\Enums\MarketingDesignAuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only Master Design audit (PRD §14.4, §20). Metadata never holds signed URLs or credentials.
 */
class MarketingDesignAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'marketing_design_bundle_id',
        'marketing_design_item_id',
        'marketing_design_file_id',
        'event',
        'actor_id',
        'from_status',
        'to_status',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event' => MarketingDesignAuditEvent::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Audit log Master Design bersifat append-only.'));
        static::deleting(fn (): never => throw new LogicException('Audit log Master Design bersifat append-only.'));
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(MarketingDesignBundle::class, 'marketing_design_bundle_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
