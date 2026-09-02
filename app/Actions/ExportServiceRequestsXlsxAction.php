<?php

namespace App\Actions;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportServiceRequestsXlsxAction
{
    public function execute(Builder $query, ?string $dateFrom = null, ?string $dateUntil = null): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'service_requests_');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers(), $this->headerStyle()));

        $query
            ->with(['technician:id,name,username', 'scheduledBy:id,name,username'])
            ->when($dateFrom, fn (Builder $query, string $date): Builder => $query->whereDate('scheduled_date', '>=', $date))
            ->when($dateUntil, fn (Builder $query, string $date): Builder => $query->whereDate('scheduled_date', '<=', $date))
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
            'ID', 'Kode', 'Tanggal Penjadwalan', 'Terakhir Diperbarui', 'User ID Pemohon',
            'Pemohon', 'Username Pemohon', 'Teknisi ID', 'Teknisi', 'Catatan Pemohon',
            'Lampiran', 'Status', 'Garansi Hingga', 'Catatan Klaim Garansi',
        ];
    }

    /** @return array<int, int|string> */
    private function row(ServiceRequest $request): array
    {
        return [
            $request->id,
            $this->safeText($request->code),
            $request->scheduled_date?->format('Y-m-d') ?? '',
            $request->updated_at?->format('Y-m-d H:i:s') ?? '',
            $request->scheduled_by ?? '',
            $this->safeText($request->scheduledBy?->name),
            $this->safeText($request->scheduledBy?->username),
            $request->technician_id ?? '',
            $this->safeText($request->technician?->name ?? 'Belum ditugaskan'),
            $this->safeText($request->requestor_notes),
            $this->safeText(is_array($request->attachments) ? implode(' | ', $request->attachments) : (string) ($request->attachments ?? '')),
            $request->status instanceof ServiceRequestStatus ? $request->status->getLabel() : (string) $request->status,
            $request->warranty_expires_at?->format('Y-m-d H:i:s') ?? '',
            $this->safeText($request->warranty_claim_notes),
        ];
    }

    private function filename(?string $dateFrom, ?string $dateUntil): string
    {
        $from = $dateFrom ?: 'semua';
        $until = $dateUntil ?: 'semua';

        return "permintaan-service-{$from}-sampai-{$until}.xlsx";
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
