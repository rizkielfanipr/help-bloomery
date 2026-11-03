<?php

namespace App\Models;

use App\Enums\MaterialSourcingRequestStatus;
use Database\Factories\MaterialSourcingRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A request from the Employee App for Purchasing to find a supplier for one active Master Product
 * ESB detail (docs/products/purchasing/employee-sourcing-request/prd.md). It stays separate from the
 * sourcing record until Purchasing converts it once into a standalone sourcing.
 *
 * Requester, branch, company, status, and the linked material are set by Actions only.
 */
class MaterialSourcingRequest extends Model
{
    /** @use HasFactory<MaterialSourcingRequestFactory> */
    use HasFactory;

    public const COMPANY_CODE = 'BLSS';

    public const ATTACHMENT_DISK = 'b2';

    public const MAX_ATTACHMENTS = 5;

    public const MAX_ATTACHMENT_KILOBYTES = 10240;

    /** @var list<string> */
    public const ATTACHMENT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    protected $attributes = [
        'company_code' => self::COMPANY_CODE,
    ];

    protected $fillable = [
        'request_number', 'submission_key', 'status', 'company_code',
        'requester_id', 'requester_name', 'branch_id', 'branch_name',
        'esb_product_id', 'esb_product_detail_id', 'product_code', 'product_name', 'category_name', 'sub_category_name', 'uom_name',
        'reason', 'estimated_quantity', 'target_date', 'notes', 'attachments',
        'assigned_to', 'claimed_at', 'last_decision_reason', 'last_decision_by', 'last_decision_at',
        'rnd_product_esb_material_id', 'submitted_at', 'converted_at', 'closed_at',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Request sourcing tidak dapat dihapus.');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => MaterialSourcingRequestStatus::class,
            'estimated_quantity' => 'decimal:4',
            'target_date' => 'date',
            'attachments' => 'array',
            'claimed_at' => 'datetime',
            'last_decision_at' => 'datetime',
            'submitted_at' => 'datetime',
            'converted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public static function numberFor(self $request): string
    {
        return 'MSR-'.($request->created_at ?? now())->format('Ymd').'-'.str_pad((string) $request->id, 6, '0', STR_PAD_LEFT);
    }

    /** @param Builder<self> $query */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('requester_id', $user->id);
    }

    /** @param Builder<self> $query */
    public function scopeActionable(Builder $query): void
    {
        $query->whereIn('status', MaterialSourcingRequestStatus::actionableValues());
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function lastDecisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_decision_by');
    }

    public function sourcingMaterial(): BelongsTo
    {
        return $this->belongsTo(RndProductEsbMaterial::class, 'rnd_product_esb_material_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(MaterialSourcingRequestStatusHistory::class)->orderBy('id');
    }

    /** @return list<array{path: string, name: string, mime: string, size: int}> */
    public function attachmentList(): array
    {
        return array_values(array_filter((array) $this->attachments, fn ($attachment): bool => is_array($attachment) && filled($attachment['path'] ?? null)));
    }

    public function formattedQuantity(): ?string
    {
        if ($this->estimated_quantity === null) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $this->estimated_quantity, 4, ',', '.'), '0'), ',').' '.$this->uom_name;
    }
}
