<?php

namespace App\Actions\QualityControl;

use App\Models\QualityControlItemJournal;
use App\Models\User;
use App\Services\Esb\EsbItemJournalService;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SubmitQualityControlItemJournalAction
{
    public function __construct(private readonly EsbItemJournalService $service) {}

    public function execute(User $actor, QualityControlItemJournal $journal): void
    {
        abort_unless($actor->can('submit quality control item journals'), 403);
        abort_unless($actor->can('view all quality control item journals') || $journal->created_by === $actor->id, 403);
        abort_if(filled($journal->item_journal_number), 422, 'Item Journal sudah memiliki nomor ESB.');
        try {
            $result = $this->service->create($journal->esb_comcode, $journal->request_payload);
            $journal->update(['item_journal_number' => $result['itemJournalNum'], 'response_payload' => $result['response'], 'submitted_at' => now(), 'status' => 'succeeded']);
            if ($journal->attachments()->exists()) {
                $this->uploadAttachments($actor, $journal);
            }
        } catch (Throwable $exception) {
            $journal->update(['status' => $journal->item_journal_number ? 'attachment_failed' : 'verification_required', 'last_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function uploadAttachments(User $actor, QualityControlItemJournal $journal): void
    {
        abort_unless($actor->can('view all quality control item journals') || $journal->created_by === $actor->id, 403);
        $service = $this->service;
        $attachments = $journal->attachments()->get();
        $files = $attachments->map(fn ($attachment): array => ['name' => $attachment->original_name, 'contents' => Storage::disk('b2')->get($attachment->file_path), 'mime' => $attachment->mime_type])->all();
        $urls = $service->uploadAttachments($journal->esb_comcode, $journal->item_journal_number, $files);
        foreach ($attachments->values() as $index => $attachment) {
            $attachment->update(['esb_url' => $urls[$index] ?? null, 'uploaded_to_esb_at' => now()]);
        }
        $journal->update(['status' => 'succeeded', 'last_error' => null]);
    }
}
