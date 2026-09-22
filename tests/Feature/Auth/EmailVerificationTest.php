<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;

test('legacy email verification screen remains unavailable', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/verify-email')->assertNotFound();
});

test('legacy signed email verification endpoint remains unavailable', function () {
    $user = User::factory()->unverified()->create();
    Event::fake([Verified::class]);

    $this->actingAs($user)->get("/verify-email/{$user->id}/valid-looking-hash")->assertNotFound();

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('legacy email verification endpoint does not accept an invalid hash', function () {
    $user = User::factory()->unverified()->create();
    Event::fake([Verified::class]);

    $this->actingAs($user)->get("/verify-email/{$user->id}/invalid-hash")->assertNotFound();

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
