<?php

use App\Actions\CustomerComplaint\CreateCustomerComplaintAction;
use App\Actions\CustomerComplaint\GenerateComplaintNumberAction;
use App\Enums\CustomerComplaintCategory;
use App\Enums\CustomerComplaintSource;
use App\Enums\CustomerComplaintStatus;
use App\Models\Branch;
use App\Models\CustomerComplaint;
use App\Models\CustomerComplaintActivity;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function complaintSubmissionData(int $branchId, array $overrides = []): array
{
    return array_merge([
        'branch_id' => $branchId,
        'occurred_at' => now()->subHour(),
        'source' => CustomerComplaintSource::InStore->value,
        'category' => CustomerComplaintCategory::Service->value,
        'order_reference' => 'TRX-000123',
        'customer_name' => 'Budi',
        'customer_contact' => '0812xxxx',
        'description' => 'Pelayanan lambat saat jam sibuk.',
        'attachment_paths' => null,
    ], $overrides);
}

it('creates a complaint and its first activity in one transaction', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    $complaint = app(CreateCustomerComplaintAction::class)->execute(complaintSubmissionData($branch->id), $user);

    expect($complaint->exists)->toBeTrue()
        ->and($complaint->status)->toBe(CustomerComplaintStatus::New)
        ->and($complaint->submitted_by)->toBe($user->id)
        ->and($complaint->complaint_number)->toStartWith('CMP-'.now()->format('Ymd').'-');

    $activity = CustomerComplaintActivity::query()->where('customer_complaint_id', $complaint->id)->sole();
    expect($activity->activity_type)->toBe(CustomerComplaintActivity::TYPE_CREATED)
        ->and($activity->new_status)->toBe(CustomerComplaintStatus::New->value)
        ->and($activity->created_by)->toBe($user->id);
});

it('rejects a submission for a branch the user cannot access, even if the request claims it', function () {
    $accessibleBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $accessibleBranch->id]);

    expect(fn () => app(CreateCustomerComplaintAction::class)->execute(
        complaintSubmissionData($otherBranch->id),
        $user,
    ))->toThrow(ValidationException::class);

    expect(CustomerComplaint::query()->count())->toBe(0);
});

it('generates sequential, date-prefixed complaint numbers', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $action = app(CreateCustomerComplaintAction::class);

    $first = $action->execute(complaintSubmissionData($branch->id), $user);
    $second = $action->execute(complaintSubmissionData($branch->id), $user);

    $today = now()->format('Ymd');
    expect($first->complaint_number)->toBe("CMP-{$today}-0001")
        ->and($second->complaint_number)->toBe("CMP-{$today}-0002");
});

/**
 * True multi-connection concurrency cannot be exercised inside a single-process Pest run against
 * an in-memory SQLite database. This instead forces the exact failure mode the retry loop exists
 * for: a generated number that collides with a row committed by a "concurrent" request, by
 * stubbing the generator to hand back an already-taken number once before recovering. It proves
 * CreateCustomerComplaintAction's catch-and-retry path (not a bare count+1) is what keeps the
 * submission from failing outright, per docs/customer-complaints-prd.md §13.
 */
it('retries instead of failing when the generated complaint number collides with an existing one', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $today = now()->format('Ymd');
    $takenNumber = "CMP-{$today}-0001";
    CustomerComplaint::factory()->create(['complaint_number' => $takenNumber, 'branch_id' => $branch->id]);

    $stub = new class extends GenerateComplaintNumberAction
    {
        private int $calls = 0;

        public function execute(): string
        {
            $this->calls++;

            // First attempt pretends it did not see the row above — simulating the race window
            // where two requests both read the same "next" number before either commits.
            return $this->calls === 1 ? 'CMP-'.now()->format('Ymd').'-0001' : parent::execute();
        }
    };
    app()->instance(GenerateComplaintNumberAction::class, $stub);

    $complaint = app(CreateCustomerComplaintAction::class)->execute(complaintSubmissionData($branch->id), $user);

    expect($complaint->complaint_number)->not->toBe($takenNumber)
        ->and(CustomerComplaint::query()->count())->toBe(2);
});
