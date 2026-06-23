<?php

use App\Filament\Casual\Pages\PositionsPage;
use App\Models\CasualPositionOpening;
use App\Models\CasualPositionRegistration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
});

function casualUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('casual_staff');

    return $user;
}

it('shows registrations and available openings on the positions page', function () {
    $opening = CasualPositionOpening::factory()->create([
        'total_slots' => 5,
        'work_date' => today()->addDay(),
    ]);
    $user = casualUser();
    $user->update(['casual_position_id' => $opening->casual_position_id]);
    CasualPositionRegistration::create([
        'casual_position_opening_id' => $opening->id,
        'user_id' => $user->id,
    ]);

    actingAs($user);

    Livewire::test(PositionsPage::class)
        ->assertOk();
});

it('shows available openings on the positions page', function () {
    $opening = CasualPositionOpening::factory()->create([
        'is_active' => true,
        'work_date' => today()->addDay(),
        'total_slots' => 3,
    ]);

    $user = casualUser();
    actingAs($user);

    Livewire::test(PositionsPage::class)
        ->assertSee($opening->casualPosition->name);
});

it('registers a user for an opening', function () {
    $opening = CasualPositionOpening::factory()->create([
        'total_slots' => 5,
        'work_date' => today()->addDay(),
    ]);

    $user = casualUser();
    actingAs($user);

    Livewire::test(PositionsPage::class)
        ->call('registerOpening', $opening->id);

    expect(CasualPositionRegistration::where('user_id', $user->id)
        ->where('casual_position_opening_id', $opening->id)
        ->exists())->toBeTrue();
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

    Livewire::test(PositionsPage::class)
        ->call('registerOpening', $opening->id);

    expect(CasualPositionRegistration::where('user_id', $user->id)->exists())->toBeFalse();
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

    $component = Livewire::test(PositionsPage::class);
    $openings = $component->instance()->availableOpenings;

    expect($openings->pluck('id'))
        ->not->toContain($pastOpening->id)
        ->not->toContain($closedOpening->id);
});
