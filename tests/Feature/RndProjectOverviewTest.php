<?php

use App\Actions\ArchiveRndProjectAction;
use App\Actions\RestoreRndProjectAction;
use App\Actions\Rnd\Project\UpdateRndProjectOverviewAction;
use App\Filament\Helpdesk\Resources\Projects\Pages\ListProjects;
use App\Filament\Helpdesk\Resources\Projects\Pages\ViewProject;
use App\Models\RndProject;
use App\Models\User;
use App\Services\Rnd\RndRichContentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    Storage::fake('b2');

    $this->editor = User::factory()->create(['is_active' => true]);
    $this->editor->givePermissionTo(['access backoffice', 'view rnd projects', 'edit rnd projects']);
    $this->viewer = User::factory()->create(['is_active' => true]);
    $this->viewer->givePermissionTo(['access backoffice', 'view rnd projects']);

    $this->project = RndProject::factory()->create([
        'name' => 'Overview Project',
        'description' => 'Ringkasan <b>polos</b> Project',
        'start_date' => today()->subDay(),
        'end_date' => today()->addMonth(),
    ]);
    $this->prefix = "rnd/project-overviews/{$this->project->id}/rich-editor/";
});

function sanitizeOverview(?string $html, string $prefix = 'rnd/project-overviews/1/rich-editor/'): ?string
{
    return app(RndRichContentService::class)->sanitize($html, $prefix, 'Gambar');
}

it('adds a nullable overview column that can be rolled back without touching description', function () {
    $migration = require database_path('migrations/2026_11_02_111454_add_overview_html_to_rnd_projects_table.php');

    expect(Schema::hasColumn('rnd_projects', 'overview_html'))->toBeTrue()
        ->and($this->project->fresh()->overview_html)->toBeNull();

    $migration->down();
    expect(Schema::hasColumn('rnd_projects', 'overview_html'))->toBeFalse()
        ->and(Schema::hasColumn('rnd_projects', 'description'))->toBeTrue();

    $migration->up();
    expect(Schema::hasColumn('rnd_projects', 'overview_html'))->toBeTrue();
});

it('shows an empty state with a call to action only to editors', function () {
    $this->actingAs($this->editor);
    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertSee('Project Overview')
        ->assertSee('Project Overview belum ditambahkan')
        ->assertSee('Tambahkan Overview')
        ->assertDontSee('Edit Overview');

    $this->actingAs($this->viewer);
    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertSee('Project Overview belum ditambahkan')
        ->assertDontSee('Tambahkan Overview')
        ->assertActionHidden('editProjectOverview');
});

it('lets an editor write and a viewer read the overview on every project type', function (string $state) {
    $factory = RndProject::factory();
    $project = ($state === 'releaseProduct' ? $factory : $factory->{$state}())->create();

    $this->actingAs($this->editor);
    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->callAction('editProjectOverview', ['content' => '<h2>Tujuan</h2><p><strong>Rilis</strong> menu</p><ul><li>Satu</li></ul>'])
        ->assertHasNoActionErrors()
        ->assertSee('Edit Overview')
        ->assertSeeHtml('<h2>Tujuan</h2>');

    expect($project->fresh()->overview_html)->toContain('<strong>Rilis</strong>')->toContain('<ul>');

    $this->actingAs($this->viewer);
    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->assertSeeHtml('<h2>Tujuan</h2>')
        ->assertDontSee('Edit Overview');
})->with(['release product' => 'releaseProduct', 'premix' => 'premix', 'partnership' => 'partnership']);

it('forbids saving without the edit permission on the action and the server', function () {
    $this->project->update(['overview_html' => '<p>Lama</p>']);
    $this->actingAs($this->viewer);

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->call('mountAction', 'editProjectOverview')
        ->assertActionNotMounted('editProjectOverview');

    expect(fn () => app(UpdateRndProjectOverviewAction::class)->execute($this->viewer, $this->project, '<p>Baru</p>'))
        ->toThrow(AuthorizationException::class)
        ->and($this->project->fresh()->overview_html)->toBe('<p>Lama</p>');
});

it('normalizes semantically empty content to null', function (?string $html) {
    $this->project->update(['overview_html' => '<p>Lama</p>']);

    app(UpdateRndProjectOverviewAction::class)->execute($this->editor, $this->project, $html);

    expect($this->project->fresh()->overview_html)->toBeNull();
})->with([
    'null' => [null],
    'empty string' => [''],
    'empty paragraph' => ['<p></p>'],
    'whitespace and breaks' => ['<p> <br></p><p>&nbsp;</p>'],
    'only unsafe markup' => ['<script>alert(1)</script>'],
    'empty list' => ['<ul><li></li></ul>'],
]);

it('removes unsafe markup from overview content', function (string $payload, array $forbidden, ?string $kept = null) {
    $html = sanitizeOverview($payload);

    foreach ($forbidden as $fragment) {
        expect(mb_strtolower((string) $html))->not->toContain($fragment);
    }

    if ($kept !== null) {
        expect($html)->toContain($kept);
    }
})->with([
    'script' => ['<p>Aman</p><script>alert(1)</script>', ['<script', 'alert'], 'Aman'],
    'style tag' => ['<p>Aman</p><style>p{color:red}</style>', ['<style', 'color:red'], 'Aman'],
    'iframe' => ['<p>Aman</p><iframe src="https://evil.test"></iframe>', ['<iframe', 'evil.test'], 'Aman'],
    'object and embed' => ['<p>Aman</p><object data="x"></object><embed src="x">', ['<object', '<embed'], 'Aman'],
    'svg' => ['<p>Aman</p><svg onload="alert(1)"><script>alert(1)</script></svg>', ['<svg', 'onload', 'alert'], 'Aman'],
    'quoted handler' => ['<p onclick="alert(1)">Aman</p>', ['onclick', 'alert'], 'Aman'],
    'unquoted handler' => ['<p onclick=alert(1)>Aman</p>', ['onclick', 'alert'], 'Aman'],
    'handler with odd spacing' => ['<p/onclick=alert(1)>Aman</p>', ['onclick', 'alert'], 'Aman'],
    'handler on image' => ['<img src=x onerror=alert(1)>', ['onerror', 'alert', '<img']],
    'style and class attributes' => ['<p style="position:fixed" class="x" id="y" data-x="z">Aman</p>', ['style=', 'class=', 'id=', 'data-x'], 'Aman'],
    'javascript link' => ['<a href="javascript:alert(1)">T</a>', ['javascript', 'href', 'alert'], 'T'],
    'mixed case javascript link' => ['<a href="JaVaScRiPt:alert(1)">T</a>', ['javascript', 'href'], 'T'],
    'entity encoded javascript link' => ['<a href="jav&#x61;script:alert(1)">T</a>', ['javascript', 'href'], 'T'],
    'tab in javascript link' => ['<a href="jav&#09;ascript:alert(1)">T</a>', ['script:', 'href'], 'T'],
    'leading whitespace javascript link' => ['<a href=" &#14; javascript:alert(1)">T</a>', ['javascript', 'href'], 'T'],
    'data link' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">T</a>', ['data:', 'href'], 'T'],
    'vbscript link' => ['<a href="vbscript:x">T</a>', ['vbscript', 'href'], 'T'],
    'protocol relative link' => ['<a href="//evil.test/x">T</a>', ['evil.test', 'href'], 'T'],
    'backslash link' => ['<a href="\\\\evil.test">T</a>', ['evil.test', 'href'], 'T'],
    'slash backslash link' => ['<a href="/\\evil.test">T</a>', ['evil.test', 'href'], 'T'],
    'attribute breakout' => ['<a href="https://x.test/&quot; onclick=&quot;alert(1)">T</a>', ['<a href="https://x.test/" onclick'], 'T'],
    'encoded tag in text' => ['<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', ['<script'], '&lt;script&gt;'],
]);

it('keeps allowed formatting and safe links', function () {
    $html = sanitizeOverview(
        '<h1>A</h1><h2>B</h2><h3>C</h3><blockquote>D</blockquote><p><strong>E</strong><b>F</b><em>G</em><i>H</i><u>I</u><s>J</s><br></p><ol><li>K</li></ol><ul><li>L</li></ul>'
        .'<a href="/resep/1" onclick="x()" class="y">R</a><a href="http://a.test/x">H</a><a href="https://a.test/?q=1&amp;r=2">S</a><a href="mailto:a@b.test">M</a><a href="Https://a.test">C</a>'
    );

    expect($html)->toContain('<h1>A</h1>', '<h2>B</h2>', '<h3>C</h3>', '<blockquote>D</blockquote>', '<strong>E</strong>', '<b>F</b>', '<em>G</em>', '<i>H</i>', '<u>I</u>', '<s>J</s>', '<br', '<ol><li>K</li></ol>', '<ul><li>L</li></ul>')
        ->toContain('<a href="/resep/1" target="_blank" rel="noopener noreferrer">R</a>')
        ->toContain('<a href="http://a.test/x" target="_blank" rel="noopener noreferrer">H</a>')
        ->toContain('<a href="https://a.test/?q=1&amp;r=2" target="_blank" rel="noopener noreferrer">S</a>')
        ->toContain('<a href="mailto:a@b.test" target="_blank" rel="noopener noreferrer">M</a>')
        ->toContain('<a href="Https://a.test" target="_blank" rel="noopener noreferrer">C</a>');
});

it('is deterministic and stable when sanitizing sanitized content', function () {
    $input = '<h2 onclick="x()">A</h2><img data-id="rnd/project-overviews/1/rich-editor/a.jpg" width="640" height="50%"><a href="https://a.test/?a=1">B</a>';
    $once = sanitizeOverview($input);

    expect(sanitizeOverview($input))->toBe($once)
        ->and(sanitizeOverview($once))->toBe($once);
});

it('accepts only images stored directly under the project attachment prefix', function (string $image, bool $kept) {
    $html = sanitizeOverview('<p>A</p>'.$image);

    expect(str_contains((string) $html, '<img'))->toBe($kept);
})->with([
    'own attachment' => ['<img data-id="rnd/project-overviews/1/rich-editor/a-1_b.jpg">', true],
    'own attachment with a src' => ['<img data-id="rnd/project-overviews/1/rich-editor/a.jpg" src="https://evil.test/a.jpg">', true],
    'another project' => ['<img data-id="rnd/project-overviews/2/rich-editor/a.jpg">', false],
    'project id prefix collision' => ['<img data-id="rnd/project-overviews/11/rich-editor/a.jpg">', false],
    'bom attachment' => ['<img data-id="rnd/bom-instructions/1/1/rich-editor/a.jpg">', false],
    'path traversal' => ['<img data-id="rnd/project-overviews/1/rich-editor/../../2/rich-editor/a.jpg">', false],
    'encoded traversal' => ['<img data-id="rnd/project-overviews/1/rich-editor/%2e%2e/a.jpg">', false],
    'entity traversal' => ['<img data-id="rnd/project-overviews/1/rich-editor/&#46;&#46;&#47;a.jpg">', false],
    'nested directory' => ['<img data-id="rnd/project-overviews/1/rich-editor/x/a.jpg">', false],
    'bare prefix' => ['<img data-id="rnd/project-overviews/1/rich-editor/">', false],
    'base64' => ['<img src="data:image/png;base64,AAAA">', false],
    'external' => ['<img src="https://evil.test/a.jpg">', false],
    'no source' => ['<img>', false],
]);

it('keeps only safe bounded image dimensions', function () {
    $prefix = 'rnd/project-overviews/1/rich-editor/';

    expect(sanitizeOverview('<img data-id="'.$prefix.'a.jpg" width="640px" height="50%" style="position:fixed">'))
        ->toBe('<img data-id="'.$prefix.'a.jpg" alt="Gambar" width="640px" height="50%">')
        ->and(sanitizeOverview('<img data-id="'.$prefix.'a.jpg" width="99999" height="500%">'))
        ->toBe('<img data-id="'.$prefix.'a.jpg" alt="Gambar">')
        ->and(sanitizeOverview('<img data-id="'.$prefix.'a.jpg" width="calc(1px)" height="0">'))
        ->toBe('<img data-id="'.$prefix.'a.jpg" alt="Gambar">');
});

it('does not render a row containing unsafe html even when written outside the action', function () {
    DB::table('rnd_projects')->where('id', $this->project->id)->update([
        'overview_html' => '<p onclick=alert(1)>Aman</p><script>alert("overview")</script><img src="https://evil.test/a.jpg">',
    ]);
    $this->actingAs($this->viewer);

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertSee('Aman')
        ->assertDontSeeHtml('onclick=alert')
        ->assertDontSeeHtml('alert("overview")')
        ->assertDontSeeHtml('evil.test');
});

it('stores an uploaded image privately under the project prefix and renders it to a viewer', function (string $name, string $mime) {
    $this->actingAs($this->editor);
    $file = UploadedFile::fake()->create($name, 100, $mime);

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->mountAction('editProjectOverview')
        ->set('componentFileAttachments.mountedActions.0.data.content.upload-1', $file)
        ->fillForm(['content' => '<p>Foto</p><img data-id="upload-1" src="blob:x">'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $html = $this->project->fresh()->overview_html;
    expect($html)->toContain('data-id="'.$this->prefix)->not->toContain('upload-1')->not->toContain('blob:');

    $paths = app(RndRichContentService::class)->attachmentPaths($html, $this->prefix);
    expect($paths)->toHaveCount(1);
    Storage::disk('b2')->assertExists($paths[0]);

    $this->actingAs($this->viewer);
    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertSeeHtml('src="')
        ->assertSeeHtml(basename($paths[0]));
})->with([
    'jpeg' => ['foto.jpg', 'image/jpeg'],
    'png' => ['foto.png', 'image/png'],
    'webp' => ['foto.webp', 'image/webp'],
]);

it('rejects uploads with a wrong type or above the size limit and stores nothing', function (string $name, int $kilobytes, string $mime) {
    $this->actingAs($this->editor);
    $file = UploadedFile::fake()->create($name, $kilobytes, $mime);

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->mountAction('editProjectOverview')
        ->set('componentFileAttachments.mountedActions.0.data.content.upload-1', $file)
        ->fillForm(['content' => '<p>Berkas</p><img data-id="upload-1">'])
        ->callMountedAction()
        ->assertHasFormErrors(['content'], 'mountedActionSchema0');

    expect($this->project->fresh()->overview_html)->toBeNull()
        ->and(Storage::disk('b2')->allFiles('rnd/project-overviews'))->toBe([]);
})->with([
    'pdf' => ['berkas.pdf', 100, 'application/pdf'],
    'video' => ['klip.mp4', 100, 'video/mp4'],
    'svg' => ['gambar.svg', 10, 'image/svg+xml'],
    'gif' => ['gambar.gif', 100, 'image/gif'],
    'over 8 MB' => ['besar.jpg', 8193, 'image/jpeg'],
]);

it('stores nothing when the modal is cancelled after an upload', function () {
    $this->actingAs($this->editor);

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->mountAction('editProjectOverview')
        ->set('componentFileAttachments.mountedActions.0.data.content.upload-1', UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg'))
        ->fillForm(['content' => '<img data-id="upload-1">'])
        ->unmountAction();

    expect(Storage::disk('b2')->allFiles('rnd/project-overviews'))->toBe([])
        ->and($this->project->fresh()->overview_html)->toBeNull();
});

it('rejects an attachment of another project in the editor and keeps the draft open', function () {
    $this->actingAs($this->editor);
    $foreignPath = 'rnd/project-overviews/999999/rich-editor/rahasia.jpg';
    Storage::disk('b2')->put($foreignPath, 'bytes');

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->callAction('editProjectOverview', ['content' => '<p>Draft</p><img data-id="'.$foreignPath.'">'])
        ->assertHasFormErrors(['content'], 'mountedActionSchema0')
        ->assertActionMounted('editProjectOverview');

    expect($this->project->fresh()->overview_html)->toBeNull();
    Storage::disk('b2')->assertExists($foreignPath);
});

it('deletes only attachments removed from the content after a successful save', function () {
    Storage::disk('b2')->put($this->prefix.'keep.jpg', 'keep');
    Storage::disk('b2')->put($this->prefix.'drop.jpg', 'drop');
    $action = app(UpdateRndProjectOverviewAction::class);

    $action->execute($this->editor, $this->project, '<img data-id="'.$this->prefix.'keep.jpg"><img data-id="'.$this->prefix.'drop.jpg">');
    $action->execute($this->editor, $this->project, '<p>Teks</p><img data-id="'.$this->prefix.'keep.jpg">');

    Storage::disk('b2')->assertExists($this->prefix.'keep.jpg');
    Storage::disk('b2')->assertMissing($this->prefix.'drop.jpg');

    $action->execute($this->editor, $this->project, null);

    Storage::disk('b2')->assertMissing($this->prefix.'keep.jpg');
});

it('never deletes a file of another project through a tampered path', function () {
    $foreignPath = 'rnd/project-overviews/999999/rich-editor/rahasia.jpg';
    Storage::disk('b2')->put($foreignPath, 'bytes');
    $action = app(UpdateRndProjectOverviewAction::class);

    $action->execute($this->editor, $this->project, '<img data-id="'.$this->prefix.'../../999999/rich-editor/rahasia.jpg"><p>A</p>');
    $action->execute($this->editor, $this->project, '<p>B</p>');

    Storage::disk('b2')->assertExists($foreignPath);
});

it('removes new uploads and keeps the previous content when the save fails', function () {
    $this->actingAs($this->editor);
    Storage::disk('b2')->put($this->prefix.'old.jpg', 'old');
    Storage::disk('b2')->put($this->prefix.'new.jpg', 'new');
    $previous = '<p>Lama</p><img data-id="'.$this->prefix.'old.jpg" alt="Gambar Project Overview">';
    $this->project->update(['overview_html' => $previous]);
    Event::listen('eloquent.saving: '.RndProject::class, function (): void {
        throw new RuntimeException('database unavailable');
    });

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->callAction('editProjectOverview', ['content' => '<p>Baru</p><img data-id="'.$this->prefix.'new.jpg">'])
        ->assertNotified('Project Overview gagal disimpan')
        ->assertActionMounted('editProjectOverview');

    expect($this->project->fresh()->overview_html)->toBe($previous);
    Storage::disk('b2')->assertExists($this->prefix.'old.jpg');
    Storage::disk('b2')->assertMissing($this->prefix.'new.jpg');
});

it('keeps the saved content when deleting an orphan fails', function () {
    Storage::disk('b2')->put($this->prefix.'drop.jpg', 'drop');
    $this->project->update(['overview_html' => '<img data-id="'.$this->prefix.'drop.jpg">']);
    $disk = Mockery::mock(Storage::disk('b2'));
    $disk->shouldReceive('delete')->andThrow(new RuntimeException('storage unavailable'));
    Storage::set('b2', $disk);

    app(UpdateRndProjectOverviewAction::class)->execute($this->editor, $this->project, '<p>Baru</p>');

    expect($this->project->fresh()->overview_html)->toBe('<p>Baru</p>');
});

it('rejects content above the maximum length without saving', function () {
    $this->project->update(['overview_html' => '<p>Lama</p>']);
    $html = '<p>'.str_repeat('a', RndRichContentService::MAX_HTML_LENGTH).'</p>';

    expect(fn () => app(UpdateRndProjectOverviewAction::class)->execute($this->editor, $this->project, $html))
        ->toThrow(ValidationException::class)
        ->and($this->project->fresh()->overview_html)->toBe('<p>Lama</p>');
});

it('keeps the overview and its files through archive and restore', function () {
    Storage::disk('b2')->put($this->prefix.'a.jpg', 'bytes');
    app(UpdateRndProjectOverviewAction::class)->execute($this->editor, $this->project, '<p>Isi</p><img data-id="'.$this->prefix.'a.jpg">');
    $html = $this->project->fresh()->overview_html;

    app(ArchiveRndProjectAction::class)->execute($this->project);
    expect(RndProject::withTrashed()->find($this->project->id)->overview_html)->toBe($html);

    app(RestoreRndProjectAction::class)->execute(RndProject::withTrashed()->find($this->project->id));
    expect($this->project->fresh()->overview_html)->toBe($html);
    Storage::disk('b2')->assertExists($this->prefix.'a.jpg');
});

it('updates the project timestamp without changing description when saving', function () {
    $this->travelTo(now()->addHour());

    app(UpdateRndProjectOverviewAction::class)->execute($this->editor, $this->project, '<p>Isi</p>');

    $fresh = $this->project->fresh();
    expect($fresh->description)->toBe('Ringkasan <b>polos</b> Project')
        ->and($fresh->updated_at->equalTo(now()->startOfSecond()))->toBeTrue();
});

it('keeps description as escaped plain text and leaves the overview out of lists and search', function () {
    $this->project->update(['overview_html' => '<p>KATAOVERVIEWUNIK</p>']);
    $this->actingAs($this->editor);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $list = Livewire::test(ListProjects::class)
        ->assertSee('Overview Project')
        ->assertSee('Ringkasan <b>polos</b> Project')
        ->assertDontSeeHtml('Ringkasan <b>polos</b>')
        ->assertDontSee('KATAOVERVIEWUNIK');
    $projectQueries = collect(DB::getQueryLog())->pluck('query')
        ->filter(fn (string $query): bool => preg_match('/from\s+[`"]rnd_projects[`"]/i', $query) === 1);
    DB::disableQueryLog();

    expect($projectQueries)->not->toBeEmpty()
        ->and($projectQueries->filter(fn (string $query): bool => str_contains($query, '`rnd_projects`.*') || str_contains($query, '"rnd_projects".*') || str_contains($query, 'select *') && ! str_contains($query, 'count(')))->toBeEmpty();

    $list->set('projectSearch', 'KATAOVERVIEWUNIK')->assertDontSee('Overview Project');
    $list->set('projectSearch', 'Ringkasan')->assertSee('Overview Project');

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertSee('Ringkasan <b>polos</b> Project')
        ->assertDontSeeHtml('Ringkasan <b>polos</b>');
});

it('keeps the plain description form with a helper on create and edit', function () {
    $this->actingAs($this->editor);
    $helper = 'Ringkasan singkat untuk daftar dan pencarian.';

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->call('openEditProjectModal')
        ->assertSee($helper)
        ->assertSeeHtml('wire:model="editProjectDescription"')
        ->set('editProjectDescription', '<b>tetap polos</b>')
        ->call('saveProjectInformation')
        ->assertHasNoErrors();

    expect($this->project->fresh()->description)->toBe('<b>tetap polos</b>')
        ->and(file_get_contents(resource_path('views/filament/helpdesk/rnd-projects/index.blade.php')))->toContain($helper)->not->toContain('RichEditor');
});

it('mounts the rich editor only after the modal is opened and uses the BOM toolbar', function () {
    $this->actingAs($this->editor);

    $page = Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertDontSeeHtml('fi-fo-rich-editor')
        ->assertDontSee('Simpan Overview')
        ->assertActionNotMounted('editProjectOverview')
        ->call('mountAction', 'editProjectOverview')
        ->assertActionMounted('editProjectOverview')
        ->assertFormFieldExists('content', 'mountedActionSchema0');

    expect($page->instance()->getMountedAction()->getModalHeading())->toBe('Edit Project Overview');

    $page->callMountedAction()->assertHasNoActionErrors();

    expect(file_get_contents(app_path('Filament/Helpdesk/Resources/Projects/Pages/ViewProject.php')))
        ->toContain("['bold', 'italic', 'underline']")
        ->toContain("['h2', 'h3']")
        ->toContain("['bulletList', 'orderedList']")
        ->toContain("['link', 'attachFiles']")
        ->toContain("['undo', 'redo']")
        ->toContain('->stickyModalHeader()')
        ->toContain('->stickyModalFooter()');
});

it('does not save when the modal is cancelled', function () {
    $this->actingAs($this->editor);
    $this->project->update(['overview_html' => '<p>Lama</p>']);

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->mountAction('editProjectOverview')
        ->assertSchemaStateSet(['content' => '<p>Lama</p>'], 'mountedActionSchema0')
        ->fillForm(['content' => '<p>Draft</p>'])
        ->unmountAction()
        ->assertActionNotMounted('editProjectOverview');

    expect($this->project->fresh()->overview_html)->toBe('<p>Lama</p>');
});

it('keeps the raw overview out of the Livewire snapshot', function () {
    $this->actingAs($this->editor);
    $this->project->update(['overview_html' => '<p>KATAOVERVIEWUNIK</p>']);

    $page = Livewire::test(ViewProject::class, ['record' => $this->project->id]);

    expect(json_encode($page->snapshot))->not->toContain('KATAOVERVIEWUNIK');
});

it('renders long overview content in a wrapping responsive container', function () {
    $this->actingAs($this->viewer);
    $this->project->update(['overview_html' => '<p>'.str_repeat('https://panjang.test/', 500).'</p>']);

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertSeeHtml('aria-labelledby="project-overview-heading"')
        ->assertSeeHtml('prose prose-sm max-w-none break-words')
        ->assertSeeHtml('dark:prose-invert')
        ->assertSeeHtml('[&_img]:max-w-full');
});
