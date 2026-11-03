<?php

namespace Database\Factories;

use App\Enums\MaterialSourcingRequestStatus;
use App\Models\Branch;
use App\Models\MaterialSourcingRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MaterialSourcingRequest>
 */
class MaterialSourcingRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'submission_key' => (string) Str::uuid(),
            'status' => MaterialSourcingRequestStatus::Submitted,
            'company_code' => MaterialSourcingRequest::COMPANY_CODE,
            'requester_id' => User::factory(),
            'requester_name' => fn (array $attributes): string => (string) User::query()->whereKey($attributes['requester_id'])->value('name'),
            'branch_id' => Branch::factory(),
            'branch_name' => fn (array $attributes): string => (string) Branch::query()->whereKey($attributes['branch_id'])->value('name'),
            'esb_product_id' => 501,
            'esb_product_detail_id' => 9001,
            'product_code' => 'BB-MATCHA',
            'product_name' => 'Matcha Uji Jepang',
            'category_name' => 'Bahan Baku',
            'sub_category_name' => 'Minuman',
            'uom_name' => 'KG',
            'reason' => 'Supplier lama tidak lagi menyediakan bahan ini.',
            'submitted_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (MaterialSourcingRequest $request): void {
            if ($request->request_number === null) {
                $request->forceFill(['request_number' => MaterialSourcingRequest::numberFor($request)])->saveQuietly();
            }
        });
    }

    public function status(MaterialSourcingRequestStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function inReview(User $assignee): static
    {
        return $this->state(fn (): array => [
            'status' => MaterialSourcingRequestStatus::InReview,
            'assigned_to' => $assignee->id,
            'claimed_at' => now(),
        ]);
    }
}
