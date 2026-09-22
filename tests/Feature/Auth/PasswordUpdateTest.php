<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('legacy standalone password update endpoint remains unavailable', function () {
    $user = User::factory()->create();
    $originalPassword = $user->password;

    $this->actingAs($user)->put('/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertNotFound();

    expect($user->fresh()->password)->toBe($originalPassword);
});

test('legacy standalone password update endpoint does not mutate data for an invalid current password', function () {
    $user = User::factory()->create();
    $originalPassword = $user->password;

    $this->actingAs($user)->put('/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertNotFound();

    expect($user->fresh()->password)->toBe($originalPassword)
        ->and(Hash::check('new-password', $user->password))->toBeFalse();
});
