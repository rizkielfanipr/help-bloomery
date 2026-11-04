<?php

namespace App\Models;

use App\Enums\VendorComplianceLiabilityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only audit of Late Delivery candidate and liability changes (PRD §19.3). */
class VendorComplianceIncidentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['vendor_compliance_incident_id', 'event', 'actor_id', 'from_liability_status', 'to_liability_status', 'reason', 'metadata'];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Riwayat Vendor Compliance tidak dapat diubah.');
        });
        static::deleting(function (): void {
            throw new LogicException('Riwayat Vendor Compliance tidak dapat dihapus.');
        });
    }

    protected function casts(): array
    {
        return [
            'from_liability_status' => VendorComplianceLiabilityStatus::class,
            'to_liability_status' => VendorComplianceLiabilityStatus::class,
            'metadata' => 'array',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(VendorComplianceIncident::class, 'vendor_compliance_incident_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
