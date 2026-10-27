<?php

use App\Actions\Rnd\Material\AmendMaterialTechnicalDataAction;
use App\Actions\Rnd\Material\ClaimMaterialReviewAction;
use App\Actions\Rnd\Material\ReturnMaterialForRevisionAction;
use App\Actions\Rnd\Material\SubmitMaterialForItReviewAction;
use App\Actions\Rnd\Material\WithdrawMaterialReviewAction;
use App\Enums\MaterialReviewStatus;
use App\Enums\NotificationDeliveryStatus;
use App\Filament\Helpdesk\Resources\MaterialReviews\Pages\ListMaterialReviews;
use App\Models\NotificationDelivery;
use App\Models\RndMaterialAuditLog;
use App\Models\RndMaterialReview;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Notifications\MaterialWorkflowNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->rnd = User::factory()->create(['is_active' => true]);
    $this->rnd->assignRole('RND_STAFF');
    $this->it = User::factory()->create(['is_active' => true]);
    $this->it->assignRole('IT_STAFF');
    $this->otherIt = User::factory()->create(['is_active' => true]);
    $this->otherIt->assignRole('IT_STAFF');
    $this->material = RndProductEsbMaterial::factory()->create(['created_by' => $this->rnd->id]);
});

function submitMaterial(RndProductEsbMaterial $material, User $actor): RndMaterialReview
{
    return app(SubmitMaterialForItReviewAction::class)->execute($material, $actor);
}

it('submits a valid draft as a locked review version with one IT notification', function () {
    Notification::fake();

    $review = submitMaterial($this->material, $this->rnd);

    $this->material->refresh();
    expect($review->submission_version)->toBe(1)
        ->and($review->status)->toBe(MaterialReviewStatus::WaitingItReview)
        ->and($review->snapshot['product_code'])->toBe($this->material->product_code)
        ->and($review->snapshot_hash)->toHaveLength(64)
        ->and($this->material->review_status)->toBe(MaterialReviewStatus::WaitingItReview)
        ->and($this->material->review_status->isEditableByRnd())->toBeFalse();
    Notification::assertSentTo($this->it, MaterialWorkflowNotification::class, fn ($notification): bool => $notification->event === 'review_submitted');
    expect(RndMaterialAuditLog::query()->where('event', 'submitted_for_review')->count())->toBe(1);
});

it('treats a repeated submit of the same data as idempotent', function () {
    $first = submitMaterial($this->material, $this->rnd);
    $second = submitMaterial($this->material->refresh(), $this->rnd);

    expect($second->id)->toBe($first->id)
        ->and(RndMaterialReview::query()->count())->toBe(1)
        ->and(NotificationDelivery::query()->count())->toBe(1);
});

it('rejects invalid unit structures before submission', function (Closure $breakUnits) {
    $breakUnits($this->material);

    expect(fn () => submitMaterial($this->material->refresh(), $this->rnd))->toThrow(ValidationException::class);
    expect(RndMaterialReview::query()->count())->toBe(0)
        ->and(NotificationDelivery::query()->count())->toBe(0);
})->with([
    'no base unit' => [fn (RndProductEsbMaterial $material) => $material->units()->update(['is_base' => false])],
    'base factor not 1' => [fn (RndProductEsbMaterial $material) => $material->units()->update(['conversion_factor' => 2])],
    'blank sku' => [fn (RndProductEsbMaterial $material) => $material->units()->update(['sku' => ' '])],
    'invalid code' => [fn (RndProductEsbMaterial $material) => $material->update(['product_code' => 'bad code'])],
]);

it('refuses submission by a user without the submit permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('edit rnd projects');

    expect(fn () => submitMaterial($this->material, $viewer))->toThrow(AuthorizationException::class);
});

it('lets one IT reviewer claim and blocks a second reviewer', function () {
    submitMaterial($this->material, $this->rnd);

    $review = app(ClaimMaterialReviewAction::class)->execute($this->material, $this->it);
    expect($review->status)->toBe(MaterialReviewStatus::UnderItReview)
        ->and($review->claimed_by)->toBe($this->it->id)
        ->and(app(ClaimMaterialReviewAction::class)->execute($this->material, $this->it)->id)->toBe($review->id);

    expect(fn () => app(ClaimMaterialReviewAction::class)->execute($this->material, $this->otherIt))->toThrow(ValidationException::class);
    expect(fn () => app(ReturnMaterialForRevisionAction::class)->execute($this->material, $this->otherIt, 'Kategori tidak sesuai standar'))->toThrow(ValidationException::class);
});

it('returns for revision with a reason and lets R&D resubmit as a new version', function () {
    Notification::fake();
    submitMaterial($this->material, $this->rnd);
    app(ClaimMaterialReviewAction::class)->execute($this->material, $this->it);

    expect(fn () => app(ReturnMaterialForRevisionAction::class)->execute($this->material, $this->it, 'kurang'))->toThrow(ValidationException::class);
    app(ReturnMaterialForRevisionAction::class)->execute($this->material, $this->it, 'Kategori harus Bahan Baku Kering');

    expect($this->material->refresh()->review_status)->toBe(MaterialReviewStatus::RevisionRequired);
    Notification::assertSentTo($this->rnd, MaterialWorkflowNotification::class, fn ($notification): bool => $notification->event === 'revision_required');

    $this->material->update(['sub_category_id' => 22]);
    $second = submitMaterial($this->material->refresh(), $this->rnd);

    expect($second->submission_version)->toBe(2)
        ->and(RndMaterialReview::query()->where('submission_version', 1)->sole()->status)->toBe(MaterialReviewStatus::RevisionRequired)
        ->and(NotificationDelivery::query()->count())->toBe(2);
});

it('records technical amendments with before/after and refreshes the approval snapshot', function () {
    submitMaterial($this->material, $this->rnd);
    app(ClaimMaterialReviewAction::class)->execute($this->material, $this->it);
    $unit = $this->material->units()->sole();
    $oldHash = RndMaterialReview::query()->sole()->snapshot_hash;

    $count = app(AmendMaterialTechnicalDataAction::class)->execute($this->material, $this->it, [
        'sub_category_id' => 25,
        'units' => [$unit->id => ['sku' => 'BBM-FIXED-GR', 'is_purchase' => false]],
    ], 'SKU mengikuti standar penamaan ESB');

    $log = RndMaterialAuditLog::query()->where('field_path', 'units.GR.sku')->sole();
    expect($count)->toBe(3)
        ->and($log->before_value)->toBe(['v' => $unit->sku])
        ->and($log->after_value)->toBe(['v' => 'BBM-FIXED-GR'])
        ->and($log->actor_id)->toBe($this->it->id)
        ->and(RndMaterialReview::query()->sole()->snapshot_hash)->not->toBe($oldHash)
        ->and(RndMaterialReview::query()->sole()->snapshot['units'][0]['sku'])->toBe('BBM-FIXED-GR');
});

it('refuses technical amendments without a reason or without the permission', function () {
    submitMaterial($this->material, $this->rnd);
    app(ClaimMaterialReviewAction::class)->execute($this->material, $this->it);

    expect(fn () => app(AmendMaterialTechnicalDataAction::class)->execute($this->material, $this->it, ['sub_category_id' => 25], ''))->toThrow(ValidationException::class)
        ->and(fn () => app(AmendMaterialTechnicalDataAction::class)->execute($this->material, $this->rnd, ['sub_category_id' => 25], 'Perubahan teknis oleh R&D'))->toThrow(AuthorizationException::class);
});

it('lets R&D withdraw an unclaimed submission', function () {
    submitMaterial($this->material, $this->rnd);

    app(WithdrawMaterialReviewAction::class)->execute($this->material, $this->rnd, 'Masih ada yang perlu dicek');

    expect($this->material->refresh()->review_status)->toBe(MaterialReviewStatus::ReviewCancelled)
        ->and($this->material->review_status->isEditableByRnd())->toBeTrue();
});

it('writes no review, delivery, or job when the submit transaction rolls back', function () {
    Http::fake();
    config(['services.telegram.enabled' => true, 'services.telegram.bot_token' => 'test-token', 'services.telegram.chats.it_review' => '-1001']);

    try {
        DB::transaction(function () {
            submitMaterial($this->material, $this->rnd);

            throw new RuntimeException('Rollback after submit');
        });
    } catch (RuntimeException) {
    }

    expect(RndMaterialReview::query()->count())->toBe(0)
        ->and(NotificationDelivery::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('records a skipped delivery without any HTTP request when Telegram is not configured', function () {
    Http::fake();

    submitMaterial($this->material, $this->rnd);

    expect(NotificationDelivery::query()->sole()->status)->toBe(NotificationDeliveryStatus::Skipped);
    Http::assertNothingSent();
});

it('lists submissions for IT with the latest review version and table-qualified columns', function () {
    Notification::fake();
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    submitMaterial($this->material, $this->rnd);
    app(ClaimMaterialReviewAction::class)->execute($this->material, $this->it);
    app(ReturnMaterialForRevisionAction::class)->execute($this->material, $this->it, 'Kategori harus Bahan Baku Kering');
    $this->material->update(['sub_category_id' => 22]);
    submitMaterial($this->material->refresh(), $this->rnd);

    $reviewQueries = [];
    DB::listen(function ($query) use (&$reviewQueries): void {
        if (str_contains($query->sql, 'latestOfMany')) {
            $reviewQueries[] = $query->sql;
        }
    });

    $this->actingAs($this->it);
    Livewire::test(ListMaterialReviews::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$this->material])
        ->assertSee($this->rnd->name);

    // SQLite tolerates bare column names in the latestOfMany self-join; MySQL rejects them as ambiguous.
    expect($reviewQueries)->not->toBeEmpty()
        ->and($reviewQueries[0])->toMatch('/^select [`"]rnd_material_reviews[`"]\.[`"]id[`"], [`"]rnd_material_reviews[`"]\.[`"]rnd_product_esb_material_id[`"]/');
});
