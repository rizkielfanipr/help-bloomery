<?php

namespace App\Actions\Rnd\InternalMemo;

use App\Enums\RndInternalMemoStatus;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoDocument;
use App\Models\User;
use App\Services\Rnd\InternalMemo\InternalMemoPdfDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * docs/rnd-internal-memo-prd.md §7.6, §16. Renders straight from the Finalized snapshot already
 * on disk-in-the-database (Menu/Material rows) — no ESB call. Every generation writes a new file
 * under this revision; nothing is ever overwritten, so an older document keeps working even
 * after a newer one is generated for the same revision.
 */
class GenerateInternalMemoPdfAction
{
    private const DISK = 'local';

    public function __construct(private InternalMemoPdfDataService $dataService) {}

    public function execute(RndInternalMemo $memo, User $actor): RndInternalMemoDocument
    {
        if ($memo->status !== RndInternalMemoStatus::Finalized) {
            throw new RuntimeException('PDF hanya dapat dibuat dari Memo yang sudah Finalized.');
        }

        $data = $this->dataService->build($memo);
        $html = view('exports.rnd-internal-memo-pdf', $data)->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $binary = $pdf->output();

        $checksum = hash('sha256', $binary);
        $fileName = sprintf('rnd-internal-memo-%s-r%d-%s.pdf', $memo->id, $memo->revision, substr($checksum, 0, 10));
        $path = "rnd-internal-memos/{$memo->id}/{$fileName}";

        Storage::disk(self::DISK)->put($path, $binary);

        return RndInternalMemoDocument::query()->create([
            'rnd_internal_memo_id' => $memo->id,
            'revision' => $memo->revision,
            'disk' => self::DISK,
            'file_path' => $path,
            'file_size' => strlen($binary),
            'checksum' => $checksum,
            'generated_by' => $actor->id,
            'generated_at' => now(),
        ]);
    }
}
