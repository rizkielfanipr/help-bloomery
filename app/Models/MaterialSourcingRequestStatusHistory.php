<?php

namespace App\Models;

use App\Enums\MaterialSourcingRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only audit of one request status change (PRD §14.2). */
class MaterialSourcingRequestStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['material_sourcing_request_id', 'from_status', 'to_status', 'actor_id', 'reason', 'metadata'];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Riwayat request sourcing tidak dapat diubah.');
        });
        static::deleting(function (): void {
            throw new LogicException('Riwayat request sourcing tidak dapat dihapus.');
        });
    }

    protected function casts(): array
    {
        return [
            'from_status' => MaterialSourcingRequestStatus::class,
            'to_status' => MaterialSourcingRequestStatus::class,
            'metadata' => 'array',
        ];
    }

    public function sourcingRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialSourcingRequest::class, 'material_sourcing_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
