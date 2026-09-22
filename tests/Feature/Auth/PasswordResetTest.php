<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('legacy password reset link screen remains unavailable', function () {
    $this->get('/forgot-password')->assertNotFound();
});

test('legacy password reset link cannot be requested', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])->assertNotFound();

    Notification::assertNotSentTo($user, ResetPassword::class);
});

test('legacy password reset form remains unavailable', function () {
    $this->get('/reset-password/test-token')->assertNotFound();
});

test('legacy password reset endpoint remains unavailable', function () {
    $user = User::factory()->create();
    $originalPassword = $user->password;

    $this->post('/reset-password', [
        'token' => 'test-token',
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertNotFound();

    expect($user->fresh()->password)->toBe($originalPassword);
});
