<?php

use App\Actions\Rnd\Material\SubmitMaterialForItReviewAction;
use App\Enums\MaterialReviewStatus;
use App\Enums\NotificationDeliveryStatus;
use App\Jobs\SendNotificationDeliveryJob;
use App\Models\NotificationDelivery;
use App\Models\RndProductEsbMaterial;
use App\Models\User;
use App\Services\Notifications\NotificationDeliveryRecorder;
use App\Services\Telegram\TelegramClient;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config([
        'app.url' => 'https://help-bloomery.test',
        'services.telegram.enabled' => true,
        'services.telegram.bot_token' => '123456:SECRET-BOT-TOKEN',
        'services.telegram.base_url' => 'https://api.telegram.test',
        'services.telegram.chats.it_review' => '-100777',
    ]);
    $this->rnd = User::factory()->create(['is_active' => true, 'name' => 'Rina R&D']);
    $this->rnd->assignRole('RND_STAFF');
    $this->material = RndProductEsbMaterial::factory()->create(['product_name' => 'Tepung Mochi', 'product_code' => 'BBM-MOCHI', 'created_by' => $this->rnd->id]);
});

it('sends one Telegram message after commit with the review summary and link', function () {
    Http::fake(['https://api.telegram.test/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 10, 21)->setTime(2, 30));

    app(SubmitMaterialForItReviewAction::class)->execute($this->material, $this->rnd);

    $delivery = NotificationDelivery::query()->sole();
    expect($delivery->status)->toBe(NotificationDeliveryStatus::Sent)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->destination_key)->toBe('it_review')
        ->and($delivery->idempotency_key)->toBe("telegram:material_review_submitted:material:{$this->material->id}:v1:it_review");
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sendMessage')
        && $request['chat_id'] === '-100777'
        && str_contains($request['text'], 'Review Master Bahan Baru')
        && str_contains($request['text'], 'Tepung Mochi')
        && str_contains($request['text'], 'BBM-MOCHI')
        && str_contains($request['text'], 'Rina R&D')
        && str_contains($request['text'], 'WIB')
        && str_contains($request['text'], 'https://help-bloomery.test/material-reviews/'.$this->material->id));
});

it('titles WIP submissions as Master WIP', function () {
    Http::fake(['https://api.telegram.test/*' => Http::response(['ok' => true])]);
    $wip = RndProductEsbMaterial::factory()->wip()->create(['created_by' => $this->rnd->id]);

    app(SubmitMaterialForItReviewAction::class)->execute($wip, $this->rnd);

    Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'Review Master WIP Baru'));
});

it('keeps the review active and redacts the token when Telegram fails or times out', function (Closure $response) {
    Http::fake(['https://api.telegram.test/*' => $response]);

    app(SubmitMaterialForItReviewAction::class)->execute($this->material, $this->rnd);

    $delivery = NotificationDelivery::query()->sole();
    expect($this->material->refresh()->review_status)->toBe(MaterialReviewStatus::WaitingItReview)
        ->and($delivery->status)->toBe(NotificationDeliveryStatus::Failed)
        ->and($delivery->error_summary)->not->toBeNull()
        ->and($delivery->error_summary)->not->toContain('SECRET-BOT-TOKEN')
        ->and($delivery->error_summary)->not->toContain('-100777');
})->with([
    'explicit rejection' => [fn () => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)],
    'timeout' => [fn () => Http::failedConnection('cURL error 28: Operation timed out for https://api.telegram.test/bot123456:SECRET-BOT-TOKEN/sendMessage')],
]);

it('retries a failed delivery once on the same row without a duplicate message', function () {
    Http::fakeSequence('https://api.telegram.test/*')->push(['ok' => false, 'description' => 'Too Many Requests'], 429)->push(['ok' => true]);
    app(SubmitMaterialForItReviewAction::class)->execute($this->material, $this->rnd);
    $delivery = NotificationDelivery::query()->sole();

    expect(app(NotificationDeliveryRecorder::class)->retry($delivery))->toBeTrue();
    expect(app(NotificationDeliveryRecorder::class)->retry($delivery->refresh()))->toBeFalse();

    expect($delivery->status)->toBe(NotificationDeliveryStatus::Sent)
        ->and(NotificationDelivery::query()->count())->toBe(1);
    Http::assertSentCount(2);
});

it('dispatches the job only after commit and never twice for the same version', function () {
    Queue::fake();

    $service = app(SubmitMaterialForItReviewAction::class);
    $service->execute($this->material, $this->rnd);
    $service->execute($this->material->refresh(), $this->rnd);

    Queue::assertPushed(SendNotificationDeliveryJob::class, 1);
});

it('does not resend a delivery that was already sent', function () {
    Http::fake(['https://api.telegram.test/*' => Http::response(['ok' => true])]);
    app(SubmitMaterialForItReviewAction::class)->execute($this->material, $this->rnd);
    $delivery = NotificationDelivery::query()->sole();

    (new SendNotificationDeliveryJob($delivery->id))->handle(app(TelegramClient::class));

    Http::assertSentCount(1);
});
