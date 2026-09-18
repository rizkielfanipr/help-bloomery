<?php

use App\Actions\QualityControl\SubmitQualityControlItemJournalAction;
use App\Models\QualityControlItemJournal;
use App\Models\User;
use App\Services\Esb\EsbItemJournalService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('b2');
    $this->actor = User::factory()->create(['is_active' => true]);
    $this->actor->givePermissionTo('submit quality control item journals');
    $this->journal = QualityControlItemJournal::query()->create([
        'created_by' => $this->actor->id, 'esb_branch_id' => 1, 'esb_branch_code' => 'BLA', 'esb_comcode' => 'BLSS',
        'location_id' => 1, 'location_name' => 'Kitchen', 'journal_date' => '2026-09-18', 'status' => 'submitting', 'request_payload' => ['itemJournalDate' => '2026-09-18'],
    ]);
});

it('retains verification_required after an uncertain create failure', function () {
    $service = $this->mock(EsbItemJournalService::class);
    $service->shouldReceive('create')->once()->with('BLSS', $this->journal->request_payload)->andThrow(new ConnectionException('Connection timeout'));
    expect(fn () => app(SubmitQualityControlItemJournalAction::class)->execute($this->actor, $this->journal))->toThrow(ConnectionException::class);
    expect($this->journal->fresh()->status)->toBe('verification_required')->and($this->journal->item_journal_number)->toBeNull();
});

it('retains the remote number after attachment failure and retries only attachments', function () {
    Storage::disk('b2')->put('journals/photo.jpg', 'image');
    $this->journal->attachments()->create(['file_path' => 'journals/photo.jpg', 'original_name' => 'photo.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 5, 'uploaded_by' => $this->actor->id]);
    $service = $this->mock(EsbItemJournalService::class);
    $service->shouldReceive('create')->once()->andReturn(['itemJournalNum' => 'IJ001', 'response' => ['status' => 'ok']]);
    $service->shouldReceive('uploadAttachments')->once()->andThrow(new RuntimeException('Upload failed'));
    $service->shouldReceive('uploadAttachments')->once()->andReturn(['https://files.test/photo.jpg']);
    $action = app(SubmitQualityControlItemJournalAction::class);
    expect(fn () => $action->execute($this->actor, $this->journal))->toThrow(RuntimeException::class);
    expect($this->journal->fresh()->status)->toBe('attachment_failed')->and($this->journal->item_journal_number)->toBe('IJ001');
    $action->uploadAttachments($this->actor, $this->journal);
    expect($this->journal->fresh()->status)->toBe('succeeded')->and($this->journal->last_error)->toBeNull()
        ->and($this->journal->attachments()->first()->esb_url)->toBe('https://files.test/photo.jpg');
    expect(fn () => $action->execute($this->actor, $this->journal))->toThrow(HttpException::class);
});

it('rejects a different owner before sending a journal', function () {
    $other = User::factory()->create(['is_active' => true]);
    $other->givePermissionTo('submit quality control item journals');
    $service = $this->mock(EsbItemJournalService::class);
    $service->shouldNotReceive('create');
    expect(fn () => app(SubmitQualityControlItemJournalAction::class)->execute($other, $this->journal))->toThrow(HttpException::class);
    expect($this->journal->fresh()->status)->toBe('submitting');
});
