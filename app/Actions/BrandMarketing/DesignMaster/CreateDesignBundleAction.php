<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Enums\MarketingDesignBundleStatus;
use App\Models\MarketingDesignBundle;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates a bundle under exactly one Brand (PRD §10.1).
 */
class CreateDesignBundleAction
{
    public function __construct(
        private readonly ValidateDesignBundleData $validator,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(User $actor, array $data): MarketingDesignBundle
    {
        Gate::forUser($actor)->authorize('create', MarketingDesignBundle::class);
        $validated = $this->validator->validate($data);

        try {
            return DB::transaction(function () use ($actor, $validated): MarketingDesignBundle {
                $bundle = MarketingDesignBundle::query()->create($validated + [
                    'status' => MarketingDesignBundleStatus::Active,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                $this->audit->log(MarketingDesignAuditEvent::BundleCreated, $bundle, $actor);

                return $bundle;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->validator->duplicateCode();
        }
    }
}
