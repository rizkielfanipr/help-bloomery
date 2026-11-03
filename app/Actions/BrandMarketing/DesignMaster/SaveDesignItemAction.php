<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignItem;
use App\Models\RndProjectMarketingMaterial;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignFileStore;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Adds an item together with its design file, or updates an item's type, name, and description
 * (PRD §11.7). The production specification is saved separately. On create, the object is written
 * before the transaction; if the records cannot be saved, the object is removed again.
 */
class SaveDesignItemAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignFileStore $store,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(User $actor, MarketingDesignBundle $bundle, array $data, ?UploadedFile $file): MarketingDesignItem
    {
        $this->access->authorize($actor, 'editContent', $bundle);
        $this->access->authorize($actor, 'uploadFile', $bundle);
        $attributes = $this->validate($data);

        if ($file === null) {
            throw ValidationException::withMessages(['file' => 'File desain wajib diunggah.']);
        }

        $stored = $this->store->store($file, $bundle);

        try {
            return DB::transaction(function () use ($actor, $bundle, $attributes, $stored): MarketingDesignItem {
                $locked = $this->locks->bundle($bundle->id);
                $this->access->authorize($actor, 'editContent', $locked);
                $this->access->authorize($actor, 'uploadFile', $locked);

                $item = $locked->items()->create($attributes + ['sort_order' => (int) $locked->items()->max('sort_order') + 1]);
                $created = $item->file()->create($stored + ['uploaded_by' => $actor->id, 'uploaded_at' => now()]);
                $this->audit->log(MarketingDesignAuditEvent::ItemAdded, $locked, $actor, $item, $created, metadata: ['original_name' => $created->original_name]);

                return $item;
            });
        } catch (Throwable $exception) {
            $this->store->discard($stored['path']);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(User $actor, MarketingDesignBundle $bundle, MarketingDesignItem $item, array $data): MarketingDesignItem
    {
        $attributes = $this->validate($data);

        return DB::transaction(function () use ($actor, $bundle, $item, $attributes): MarketingDesignItem {
            $locked = $this->locks->bundle($bundle->id);
            $this->access->authorize($actor, 'editContent', $locked);

            $target = $locked->items()->findOrFail($item->id);
            $target->fill($attributes);
            $changed = array_keys($target->getDirty());
            $target->save();
            $this->audit->log(MarketingDesignAuditEvent::ItemUpdated, $locked, $actor, $target, metadata: ['fields' => $changed]);

            return $target;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, title: string, description: ?string}
     *
     * @throws ValidationException
     */
    private function validate(array $data): array
    {
        $validated = Validator::make($data, [
            'type' => ['required', Rule::in(array_keys(RndProjectMarketingMaterial::TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ], [
            'type.required' => 'Tipe item wajib dipilih.',
            'type.in' => 'Tipe item tidak valid.',
            'title.required' => 'Nama item wajib diisi.',
        ])->validate();

        return [
            'type' => $validated['type'],
            'title' => trim((string) $validated['title']),
            'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
        ];
    }
}
