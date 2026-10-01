<?php

namespace App\Actions\CustomerComplaint;

use App\Enums\CustomerComplaintStatus;
use App\Models\CustomerComplaint;
use App\Models\CustomerComplaintActivity;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * docs/customer-complaints-prd.md §7.1, §13, §14.2. Branch is re-validated server-side even
 * though the form only offers accessible branches, since the request body is not trusted
 * (§12 "validasi branch dilakukan kembali di server"). The first Activity row is created in the
 * same transaction as the complaint itself (§14.2 "Perubahan record utama dan pembuatan activity
 * dijalankan dalam transaction yang sama").
 *
 * @param  array{branch_id: int, occurred_at: string, source: string, category: string, order_reference: ?string, customer_name: ?string, customer_contact: ?string, description: string, attachment_paths: ?array}  $data
 */
class CreateCustomerComplaintAction
{
    private const MAX_NUMBER_ATTEMPTS = 3;

    public function __construct(private readonly GenerateComplaintNumberAction $numberGenerator) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data, User $actor): CustomerComplaint
    {
        if (! $actor->canAccessBranch((int) $data['branch_id'])) {
            throw ValidationException::withMessages([
                'branch_id' => 'Branch yang dipilih tidak dapat diakses.',
            ]);
        }

        for ($attempt = 1; $attempt <= self::MAX_NUMBER_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $actor): CustomerComplaint {
                    $complaint = CustomerComplaint::query()->create([
                        'complaint_number' => $this->numberGenerator->execute(),
                        'branch_id' => $data['branch_id'],
                        'occurred_at' => $data['occurred_at'],
                        'source' => $data['source'],
                        'category' => $data['category'],
                        'order_reference' => filled($data['order_reference'] ?? null) ? trim((string) $data['order_reference']) : null,
                        'customer_name' => filled($data['customer_name'] ?? null) ? trim((string) $data['customer_name']) : null,
                        'customer_contact' => filled($data['customer_contact'] ?? null) ? trim((string) $data['customer_contact']) : null,
                        'description' => trim((string) $data['description']),
                        'attachment_paths' => $data['attachment_paths'] ?? null,
                        'status' => CustomerComplaintStatus::New,
                        'submitted_by' => $actor->id,
                    ]);

                    $complaint->activities()->create([
                        'activity_type' => CustomerComplaintActivity::TYPE_CREATED,
                        'new_status' => CustomerComplaintStatus::New->value,
                        'created_by' => $actor->id,
                    ]);

                    return $complaint;
                });
            } catch (UniqueConstraintViolationException $exception) {
                $isNumberCollision = str_contains($exception->getMessage(), 'complaint_number');

                if (! $isNumberCollision || $attempt === self::MAX_NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Gagal membuat nomor komplain setelah beberapa percobaan.');
    }
}
