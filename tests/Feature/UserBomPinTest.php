<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('uses the personal hashed BOM PIN when enabled', function () {
    config()->set('rnd.bom_pin', '246810');
    $user = User::factory()->create([
        'use_bom_pin' => true,
        'bom_pin' => Hash::make('135790'),
    ]);

    expect($user->verifiesBomPin('135790'))->toBeTrue()
        ->and($user->verifiesBomPin('246810'))->toBeFalse()
        ->and($user->toArray())->not->toHaveKey('bom_pin');
});

it('rejects every BOM PIN when the personal PIN is disabled', function () {
    $user = User::factory()->create([
        'use_bom_pin' => false,
        'bom_pin' => Hash::make('135790'),
    ]);

    expect($user->hasBomPin())->toBeFalse()
        ->and($user->verifiesBomPin('246810'))->toBeFalse()
        ->and($user->verifiesBomPin('135790'))->toBeFalse();
});
