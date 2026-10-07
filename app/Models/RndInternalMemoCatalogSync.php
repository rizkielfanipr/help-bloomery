<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Global Master Menu catalog sync state for one technical context (docs/rnd-internal-memo-brand-prd.md
 * §13.5). Memo Internal always uses Company Code BLSS; the technical Branch Code comes from config.
 */
class RndInternalMemoCatalogSync extends Model
{
    public const STATUS_IDLE = 'idle';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_code',
        'technical_branch_code',
        'status',
        'menu_count',
        'last_started_at',
        'last_synced_at',
        'last_failed_at',
        'last_error',
        'triggered_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_IDLE,
    ];

    protected function casts(): array
    {
        return [
            'menu_count' => 'integer',
            'last_started_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_failed_at' => 'datetime',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }
}
