<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Models\MarketingDesignBundleVersion;
use App\Models\MarketingDesignItem;
use App\Models\RndProjectMarketingMaterial;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Adds or updates an item on a draft. A draft may stay incomplete; completeness is only enforced
 * on submit and publish (PRD §17.4).
 */
class SaveDesignItemAction
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
    public function execute(User $actor, MarketingDesignBundleVersion $version, array $data, ?MarketingDesignItem $item = null): MarketingDesignItem
    {
        $attributes = Validator::make($data, [
            'type' => ['required', Rule::in(array_keys(RndProjectMarketingMaterial::TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'dimensions' => ['nullable', 'string', 'max:255'],
            'material_specification' => ['nullable', 'string', 'max:255'],
            'finishing' => ['nullable', 'string', 'max:255'],
            'color_print_notes' => ['nullable', 'string', 'max:255'],
            'sides' => ['nullable', 'string', 'max:100'],
            'production_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'type.required' => 'Tipe item wajib dipilih.',
            'type.in' => 'Tipe item tidak valid.',
            'title.required' => 'Judul item wajib diisi.',
        ])->validate();

        return DB::transaction(function () use ($actor, $version, $item, $attributes): MarketingDesignItem {
            $locked = $this->locks->version($version->marketing_design_bundle_id, $version->id);
            $this->access->authorize($actor, 'editContent', $locked);

            if ($item === null) {
                $created = $locked->items()->create($attributes + ['sort_order' => (int) $locked->items()->max('sort_order') + 1]);
                $this->audit->log(MarketingDesignAuditEvent::ItemAdded, $locked->bundle, $actor, $locked, $created);

                return $created;
            }

            $target = $locked->items()->findOrFail($item->id);
            $target->fill($attributes);
            $changed = array_keys($target->getDirty());
            $target->save();
            $this->audit->log(MarketingDesignAuditEvent::ItemUpdated, $locked->bundle, $actor, $locked, $target, metadata: ['fields' => $changed]);

            return $target;
        });
    }
}
