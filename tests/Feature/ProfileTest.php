<?php

use App\Filament\Casual\Pages\ProfilePage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('b2');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));

    $this->user = User::factory()->create([
        'is_active' => true,
        'name' => 'Test Casual',
        'phone' => '081234567890',
    ]);
    $this->user->assignRole('CASUAL_STAFF');
    $this->actingAs($this->user);
});

test('casual profile page displays the current user information', function () {
    Livewire::test(ProfilePage::class)
        ->assertOk()
        ->assertSee('Test Casual')
        ->assertSee('081234567890')
        ->assertSee($this->user->email);
});

test('casual user can update their profile photo', function () {
    $photo = UploadedFile::fake()->image('avatar.jpg', 400, 400);

    Livewire::test(ProfilePage::class)
        ->set('photo', $photo)
        ->call('savePhoto')
        ->assertHasNoErrors();

    $path = $this->user->fresh()->avatar;

    expect($path)->not->toBeNull();
    Storage::disk('b2')->assertExists($path);
});

test('replacing a profile photo removes the previous object', function () {
    Storage::disk('b2')->put('avatars/old.jpg', 'old-avatar');
    $this->user->update(['avatar' => 'avatars/old.jpg']);

    Livewire::test(ProfilePage::class)
        ->set('photo', UploadedFile::fake()->image('new-avatar.jpg', 400, 400))
        ->call('savePhoto')
        ->assertHasNoErrors();

    Storage::disk('b2')->assertMissing('avatars/old.jpg');
    Storage::disk('b2')->assertExists($this->user->fresh()->avatar);
});

test('casual user can logout from the profile page', function () {
    Livewire::test(ProfilePage::class)->call('logout');

    $this->assertGuest();
});

test('guest is redirected from the casual profile page to login', function () {
    auth()->logout();

    $this->get(ProfilePage::getUrl(panel: 'casual'))
        ->assertRedirect(route('filament.casual.auth.login'));
});
