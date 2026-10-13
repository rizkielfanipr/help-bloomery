<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * docs/daily-briefing-bundle-approval-prd.md Phase 11: every Daily Briefing back-office route keeps its
 * sidebar group expanded (the custom sidebar collapses everything else), and the bundle tooling is
 * registered where operations expect it.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $hrd = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $hrd->assignRole('HRD_STAFF');
    actingAs($hrd);
});

it('keeps the Daily Briefing sidebar group open on each of its pages', function (string $routeName) {
    $html = get(route($routeName))->assertSuccessful()->getContent();

    expect($html)->toMatch('/openGroups:\s*\[(\\\\u0022|")daily_briefing(\\\\u0022|")\]/');
})->with([
    'Monitoring Poin' => 'filament.helpdesk.resources.briefing-bundles.index',
    'Kelola Poin' => 'filament.helpdesk.resources.briefing-tasks.index',
    'Nilai Briefing' => 'filament.helpdesk.pages.briefing-score-workspace-page',
    'Pengaturan' => 'filament.helpdesk.pages.briefing-settings-page',
]);

it('points the Monitoring Poin menu at the bundle monitoring page', function () {
    get(route('filament.helpdesk.pages.briefing-score-workspace-page'))
        ->assertSee(route('filament.helpdesk.resources.briefing-bundles.index'), false)
        ->assertDontSee(route('filament.helpdesk.resources.briefing-items.index'), false);
});

it('registers the bundle maintenance commands', function (string $command) {
    expect(Artisan::all())->toHaveKey($command);
})->with(['briefing:group-legacy-bundles', 'briefing:compare-scores', 'briefing:recompute-history', 'briefing:auto-reject', 'briefing:compute-scores']);

it('schedules only the SLA review and the monthly reconciliation, never the grouping or history tools', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn (Event $event): string => (string) $event->command)
        ->filter(fn (string $command): bool => str_contains($command, 'briefing:'));

    $autoReject = collect(app(Schedule::class)->events())->first(fn (Event $event): bool => str_contains((string) $event->command, 'briefing:auto-reject'));

    expect($commands)->toHaveCount(2)
        ->and($commands->contains(fn (string $c): bool => str_contains($c, 'briefing:compute-scores')))->toBeTrue()
        ->and($autoReject->withoutOverlapping)->toBeTrue();
});
