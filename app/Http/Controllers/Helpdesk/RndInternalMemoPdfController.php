<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\RndInternalMemo;
use App\Models\RndInternalMemoDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * docs/rnd-internal-memo-prd.md §7.6, §16. The only way to reach a generated PDF; the file
 * itself lives on a private disk, never a publicly reachable path.
 */
class RndInternalMemoPdfController extends Controller
{
    public function __invoke(int $memo, int $document): StreamedResponse
    {
        $user = auth()->user();

        $memoRecord = RndInternalMemo::query()->findOrFail($memo);
        abort_unless($user?->can('downloadPdf', $memoRecord), 403);

        $documentRecord = RndInternalMemoDocument::query()
            ->where('rnd_internal_memo_id', $memoRecord->id)
            ->findOrFail($document);

        abort_unless(Storage::disk($documentRecord->disk)->exists($documentRecord->file_path), 404, 'File dokumen tidak ditemukan.');

        $safeMemoNumber = Str::slug(Str::of($memoRecord->memo_number)->replace(['/', '\\'], ' '));
        $downloadName = sprintf(
            '%s-R%d.pdf',
            $safeMemoNumber !== '' ? $safeMemoNumber : 'memo-internal-'.$memoRecord->id,
            $documentRecord->revision,
        );

        return Storage::disk($documentRecord->disk)->download(
            $documentRecord->file_path,
            $downloadName,
        );
    }
}
