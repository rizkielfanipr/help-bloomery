<?php

namespace App\Models;

use App\Enums\KitchenProductionOrderStaffAssignmentEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit of Staff Produksi changes (docs/products/kitchen/production-staff-assignment/prd.md
 * §14.2, §19). It keeps the employee, Branch, and order identity as text so it survives deleted
 * references. Metadata never holds ESB payloads or credentials.
 */
class KitchenProductionOrderStaffAssignmentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'esb_production_order_id',
        'esb_production_order_detail_id',
        'event',
        'employee_id',
        'employee_code',
        'employee_name',
        'employee_position',
        'branch_id',
        'branch_name',
        'production_order_num',
        'bom_name',
        'product_name',
        'actor_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event' => KitchenProductionOrderStaffAssignmentEventType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Audit Staff Produksi bersifat append-only.'));
        static::deleting(fn (): never => throw new LogicException('Audit Staff Produksi bersifat append-only.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EsbProductionOrder::class, 'esb_production_order_id');
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(EsbProductionOrderDetail::class, 'esb_production_order_detail_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
