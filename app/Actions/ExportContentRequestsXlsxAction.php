<?php

namespace App\Actions;

use App\Enums\ContentRequestStatus;
use App\Models\ContentRequest;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportContentRequestsXlsxAction
{
    public function execute(Builder $query, ?string $dateFrom = null, ?string $dateUntil = null): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'content_requests_');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers(), $this->headerStyle()));

        $query
            ->with(['requester:id,name,username', 'branch:id,name'])
            ->when($dateFrom, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
            ->when($dateUntil, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))
            ->chunkById(500, function ($requests) use ($writer): void {
                foreach ($requests as $request) {
                    $writer->addRow(Row::fromValues($this->row($request)));
                }
            });

        $writer->close();

        return response()->download($path, $this->filename($dateFrom, $dateUntil), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** @return array<int, string> */
    private function headers(): array
    {
        return [
            'ID', 'Kode', 'Tanggal Pengajuan', 'Terakhir Diperbarui', 'User ID Pemohon',
            'Pemohon', 'Username Pemohon', 'Branch ID', 'Cabang', 'Judul Konten',
            'Jenis Konten', 'Platform Tujuan', 'Tujuan Konten', 'Link Contoh Konten',
            'Lampiran', 'Status', 'Admin Notes', 'Resolved At',
        ];
    }

    /** @return array<int, int|string> */
    private function row(ContentRequest $request): array
    {
        return [
            $request->id,
            $this->safeText($request->code),
            $request->created_at?->format('Y-m-d H:i:s') ?? '',
            $request->updated_at?->format('Y-m-d H:i:s') ?? '',
            $request->requester_id ?? '',
            $this->safeText($request->requester?->name),
            $this->safeText($request->requester?->username),
            $request->branch_id ?? '',
            $this->safeText($request->branch?->name),
            $this->safeText($request->judul_konten),
            $request->jenis_konten === 'video' ? 'Video' : 'Foto',
            $this->safeText($request->platform_tujuan),
            $this->safeText($request->tujuan_konten),
            $this->safeText($request->link_contoh_konten),
            $this->safeText(is_array($request->attachments) ? implode(' | ', $request->attachments) : (string) ($request->attachments ?? '')),
            $request->status instanceof ContentRequestStatus ? $request->status->getLabel() : (string) $request->status,
            $this->safeText($request->admin_notes),
            $request->resolved_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    private function filename(?string $dateFrom, ?string $dateUntil): string
    {
        $from = $dateFrom ?: 'semua';
        $until = $dateUntil ?: 'semua';

        return "permintaan-konten-{$from}-sampai-{$until}.xlsx";
    }

    private function headerStyle(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setBackgroundColor('2563EB')
            ->setFontColor(Color::WHITE);
    }

    private function safeText(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $text) === 1 ? "'{$text}" : $text;
    }
}
