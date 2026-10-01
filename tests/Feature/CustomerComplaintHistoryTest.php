<?php

use App\Filament\Casual\Pages\CustomerComplaintHistoryPage;
use App\Models\Branch;
use App\Models\CustomerComplaint;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    $this->user->givePermissionTo('create customer complaints');
});

it('lists all of the signed-in user\'s own complaints, newest occurrence first, excluding other users\'', function () {
    $otherUser = User::factory()->create(['branch_id' => $this->branch->id]);
    CustomerComplaint::factory()->count(2)->create(['submitted_by' => $otherUser->id, 'branch_id' => $this->branch->id]);

    $own = CustomerComplaint::factory()->count(7)->sequence(
        fn ($sequence) => ['occurred_at' => now()->subDays(7 - $sequence->index)],
    )->create(['submitted_by' => $this->user->id, 'branch_id' => $this->branch->id]);

    $this->actingAs($this->user);
    $listed = Livewire::test(CustomerComplaintHistoryPage::class)->instance()->complaints();

    // Unlike the old "last 5" section, the dedicated Riwayat page shows the full history.
    expect($listed)->toHaveCount(7)
        ->and($listed->pluck('submitted_by')->unique()->all())->toBe([$this->user->id])
        ->and($listed->first()->id)->toBe($own->last()->id);
});

it('still shows a complaint submitted for a branch the user no longer has access to', function () {
    $otherBranch = Branch::factory()->create();
    $complaint = CustomerComplaint::factory()->create(['submitted_by' => $this->user->id, 'branch_id' => $otherBranch->id]);

    $this->actingAs($this->user);
    $listed = Livewire::test(CustomerComplaintHistoryPage::class)->instance()->complaints();

    expect($listed->pluck('id'))->toContain($complaint->id);
});

it('expands and collapses a complaint item to show its detail', function () {
    $complaint = CustomerComplaint::factory()->create(['submitted_by' => $this->user->id, 'branch_id' => $this->branch->id, 'description' => 'Deskripsi unik komplain.']);
    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintHistoryPage::class)
        ->assertDontSee('Deskripsi unik komplain.')
        ->call('toggleItem', $complaint->id)
        ->assertSee('Deskripsi unik komplain.')
        ->call('toggleItem', $complaint->id)
        ->assertDontSee('Deskripsi unik komplain.');
});

it('links attachments through the authorized controller route, not a raw disk URL', function () {
    Storage::fake('b2');
    $path = 'customer-complaints/'.$this->branch->id.'/bukti.jpg';
    Storage::disk('b2')->put($path, 'fake-bytes');
    $complaint = CustomerComplaint::factory()->create([
        'submitted_by' => $this->user->id,
        'branch_id' => $this->branch->id,
        'attachment_paths' => [$path],
    ]);

    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintHistoryPage::class)
        ->call('toggleItem', $complaint->id)
        ->assertSeeHtml(route('helpdesk.customer-complaints.attachments.show', ['path' => $path]));
});

it('shows the Riwayat tab as active and links back to the Form page', function () {
    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintHistoryPage::class)
        ->assertSeeHtml(route('filament.casual.pages.customer-complaint-page'));
});

it('shows an empty state with a call to action when there is no history yet', function () {
    $this->actingAs($this->user);

    Livewire::test(CustomerComplaintHistoryPage::class)
        ->assertSee('Belum ada komplain')
        ->assertSeeHtml(route('filament.casual.pages.customer-complaint-page'));
});
