<?php

namespace App\Actions\CustomerComplaint;

use App\Enums\CustomerComplaintStatus;
use App\Models\Branch;
use App\Models\CustomerComplaint;
use App\Models\CustomerComplaintActivity;
use App\Models\User;
use App\Notifications\CustomerComplaintSubmittedNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

        $complaint = null;

        for ($attempt = 1; $attempt <= self::MAX_NUMBER_ATTEMPTS; $attempt++) {
            try {
                $complaint = DB::transaction(function () use ($data, $actor): CustomerComplaint {
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

                break;
            } catch (UniqueConstraintViolationException $exception) {
                $isNumberCollision = str_contains($exception->getMessage(), 'complaint_number');

                if (! $isNumberCollision || $attempt === self::MAX_NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        if (! $complaint) {
            throw new RuntimeException('Gagal membuat nomor komplain setelah beberapa percobaan.');
        }

        // Dispatched after the transaction has committed (docs/customer-complaints-prd.md §16
        // "Notifikasi tidak boleh dikirim sebelum transaction database berhasil"), matching the
        // house convention of calling Notification::send() on the line after DB::transaction()
        // returns rather than inside an afterCommit() closure (no queue connection in this app
        // has after_commit enabled).
        $recipients = $this->operationalRecipients((int) $data['branch_id']);
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new CustomerComplaintSubmittedNotification($complaint));
        }

        return $complaint;
    }

    /** @return Collection<int, User> */
    private function operationalRecipients(int $branchId): Collection
    {
        if (! Branch::query()->whereKey($branchId)->exists()) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->permission('view customer complaints')
            ->get()
            ->filter(fn (User $user): bool => $user->canAccessBranch($branchId))
            ->values();
    }
}
