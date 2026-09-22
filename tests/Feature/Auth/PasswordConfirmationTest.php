<?php

use App\Models\User;

test('legacy password confirmation screen remains unavailable', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/confirm-password')->assertNotFound();
});

test('legacy password confirmation endpoint remains unavailable for a valid password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/confirm-password', ['password' => 'password'])->assertNotFound();
});

test('legacy password confirmation endpoint remains unavailable for an invalid password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/confirm-password', ['password' => 'wrong-password'])->assertNotFound();
});
