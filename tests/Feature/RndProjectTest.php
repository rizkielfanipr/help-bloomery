<?php

use App\Filament\Helpdesk\Resources\Projects\Pages\CreateProject;
use App\Filament\Helpdesk\Resources\Projects\Pages\ListProjects;
use App\Models\RndProject;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);
});

it('renders the R&D project list and create pages', function () {
    Livewire::test(ListProjects::class)
        ->assertSee('Project')
        ->assertSee('Buat Project');

    Livewire::test(CreateProject::class)
        ->assertSee('Informasi Project')
        ->assertSee('Timeline Project')
        ->assertSee('Tambah Timeline');
});

it('creates a project with a dynamic timeline', function () {
    Livewire::test(CreateProject::class)
        ->fillForm([
            'name' => 'Seasonal Product Development',
            'description' => 'Project pengembangan menu seasonal.',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'timelines' => [[
                'title' => 'Trial Recipe',
                'description' => 'Trial resep tahap pertama.',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-07',
                'status' => 'planned',
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $project = RndProject::query()->where('name', 'Seasonal Product Development')->firstOrFail();

    expect($project->timelines)->toHaveCount(1)
        ->and($project->timelines->first()->title)->toBe('Trial Recipe')
        ->and($project->timelines->first()->status)->toBe('planned');
});
