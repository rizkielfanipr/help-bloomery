<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Models\MarketingDesignBundle;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDesignBundleAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly ValidateDesignBundleData $validator,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(User $actor, MarketingDesignBundle $bundle, array $data): MarketingDesignBundle
    {
        try {
            return DB::transaction(function () use ($actor, $bundle, $data): MarketingDesignBundle {
                $locked = $this->locks->bundle($bundle->id);
                $this->access->authorize($actor, 'update', $locked);

                // The Brand is fixed after creation: it identifies the bundle and is part of every stored file path.
                $locked->fill($this->validator->validate(array_merge($data, ['brand_id' => $locked->brand_id]), $locked) + ['updated_by' => $actor->id]);
                $changed = array_keys($locked->getDirty());
                $locked->save();

                $this->audit->log(MarketingDesignAuditEvent::BundleUpdated, $locked, $actor, metadata: ['fields' => $changed]);

                return $locked;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->validator->duplicateCode();
        }
    }
}
