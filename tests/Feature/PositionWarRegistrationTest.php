<?php

use App\Filament\Casual\Pages\SelectPosition;
use App\Models\CasualPositionOpening;
use App\Models\CasualPositionRegistration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function casualUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    return $user;
}

it('redirects to shift-detail page if user already has a position', function () {
    $opening = CasualPositionOpening::factory()->create([
        'total_slots' => 5,
        'work_date' => today()->addDay(),
    ]);
    $user = casualUser();
    $user->update([
        'casual_position_id' => $opening->casual_position_id,
        'casual_shift_id' => $opening->casual_shift_id,
    ]);
    CasualPositionRegistration::create([
        'casual_position_opening_id' => $opening->id,
        'user_id' => $user->id,
    ]);

    actingAs($user);

    Livewire::test(SelectPosition::class)
        ->assertRedirect(route('filament.casual.pages.shift-detail-page'));
});

it('shows available openings on the war page', function () {
    $opening = CasualPositionOpening::factory()->create([
        'is_active' => true,
        'work_date' => today()->addDay(),
        'total_slots' => 3,
    ]);

    $user = casualUser();
    actingAs($user);

    Livewire::test(SelectPosition::class)
        ->assertSee($opening->casualPosition->name)
        ->assertSee($opening->location);
});

it('registers a user for an opening and sets position and shift', function () {
    $opening = CasualPositionOpening::factory()->create([
        'total_slots' => 5,
        'work_date' => today()->addDay(),
    ]);

    $user = casualUser();
    actingAs($user);

    Livewire::test(SelectPosition::class)
        ->call('registerOpening', $opening->id)
        ->assertRedirect(route('filament.casual.pages.shift-detail-page'));

    $user->refresh();

    expect(CasualPositionRegistration::where('user_id', $user->id)
        ->where('casual_position_opening_id', $opening->id)
        ->exists())->toBeTrue();

    expect($user->casual_position_id)->toBe($opening->casual_position_id);
    expect($user->casual_shift_id)->toBe($opening->casual_shift_id);
});

it('denies registration when all slots are taken', function () {
    $opening = CasualPositionOpening::factory()->create([
        'total_slots' => 1,
        'work_date' => today()->addDay(),
    ]);

    // Fill the only slot with another user
    $other = casualUser();
    CasualPositionRegistration::create([
        'casual_position_opening_id' => $opening->id,
        'user_id' => $other->id,
    ]);

    $user = casualUser();
    actingAs($user);

    Livewire::test(SelectPosition::class)
        ->call('registerOpening', $opening->id);

    expect(CasualPositionRegistration::where('user_id', $user->id)->exists())->toBeFalse();
    expect($user->fresh()->casual_position_id)->toBeNull();
});

it('ignores past or closed openings', function () {
    $pastOpening = CasualPositionOpening::factory()->create([
        'work_date' => today()->subDay(),
        'is_active' => true,
    ]);
    $closedOpening = CasualPositionOpening::factory()->create([
        'work_date' => today()->addDay(),
        'is_active' => false,
    ]);

    $user = casualUser();
    actingAs($user);

    $component = Livewire::test(SelectPosition::class);
    $openings = $component->instance()->getOpenings();

    expect($openings->pluck('id'))
        ->not->toContain($pastOpening->id)
        ->not->toContain($closedOpening->id);
});
