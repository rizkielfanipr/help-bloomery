<?php

use App\Models\RndProjectTaskTemplate;
use App\Models\RndProjectTaskTemplateCheckpoint;
use Database\Seeders\RndProjectTaskTemplateSeeder;

it('seeds active starter templates with ordered, valid checkpoints', function () {
    $this->seed(RndProjectTaskTemplateSeeder::class);

    $templates = RndProjectTaskTemplate::query()->with('checkpoints')->get();

    expect($templates->pluck('name')->all())->toContain('Launching Menu Baru', 'Pengembangan Produk Baru', 'Evaluasi Pasca Launching')
        ->and($templates->every->is_active)->toBeTrue();

    $templates->each(function (RndProjectTaskTemplate $template): void {
        expect($template->checkpoints)->not->toBeEmpty()
            ->and($template->checkpoints->count())->toBeLessThanOrEqual(RndProjectTaskTemplate::MAX_CHECKPOINTS)
            ->and($template->checkpoints->pluck('sort_order')->all())->toBe(range(1, $template->checkpoints->count()));
    });

    expect(RndProjectTaskTemplate::query()->where('name', 'Launching Menu Baru')->sole()->checkpoints->last()->title)->toBe('Launching');
});

it('is idempotent and never overwrites templates edited by the team', function () {
    $this->seed(RndProjectTaskTemplateSeeder::class);

    $template = RndProjectTaskTemplate::query()->where('name', 'Launching Menu Baru')->sole();
    $template->update(['description' => 'Versi tim R&D', 'is_active' => false]);
    $template->checkpoints()->first()->update(['title' => 'Trial Resep v2']);
    $templateCount = RndProjectTaskTemplate::query()->count();
    $checkpointCount = RndProjectTaskTemplateCheckpoint::query()->count();

    $this->seed(RndProjectTaskTemplateSeeder::class);

    expect(RndProjectTaskTemplate::query()->count())->toBe($templateCount)
        ->and(RndProjectTaskTemplateCheckpoint::query()->count())->toBe($checkpointCount)
        ->and($template->fresh()->description)->toBe('Versi tim R&D')
        ->and($template->fresh()->is_active)->toBeFalse()
        ->and($template->fresh()->checkpoints->first()->title)->toBe('Trial Resep v2');
});
