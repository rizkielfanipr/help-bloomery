<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignItem;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Saves the production specification of an existing physical item (PRD §11.8). Every field is
 * optional; an incomplete specification is only flagged.
 */
class SaveDesignItemSpecificationAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(User $actor, MarketingDesignBundle $bundle, MarketingDesignItem $item, array $data): MarketingDesignItem
    {
        $attributes = Validator::make($data, [
            'dimensions' => ['nullable', 'string', 'max:255'],
            'material_specification' => ['nullable', 'string', 'max:255'],
            'finishing' => ['nullable', 'string', 'max:255'],
            'color_print_notes' => ['nullable', 'string', 'max:255'],
            'sides' => ['nullable', 'string', 'max:100'],
            'production_notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $bundle, $item, $attributes): MarketingDesignItem {
            $locked = $this->locks->bundle($bundle->id);
            $this->access->authorize($actor, 'editContent', $locked);

            $target = $locked->items()->findOrFail($item->id);

            if (! $target->isPhysical()) {
                throw ValidationException::withMessages(['type' => 'Spesifikasi produksi hanya untuk item fisik.']);
            }

            $target->fill(array_map(fn (mixed $value): ?string => filled($value) ? trim((string) $value) : null, $attributes));
            $changed = array_keys($target->getDirty());
            $target->save();
            $this->audit->log(MarketingDesignAuditEvent::ItemSpecificationUpdated, $locked, $actor, $target, metadata: ['fields' => $changed]);

            return $target;
        });
    }
}
