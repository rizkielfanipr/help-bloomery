<?php

namespace App\Actions;

use App\Enums\PurchaseRequestStatus;
use App\Enums\PurchaseType;
use App\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportPurchaseRequestsXlsxAction
{
    public function execute(Builder $query, ?string $dateFrom = null, ?string $dateUntil = null): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'purchase_requests_');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers(), $this->headerStyle()));

        $query
            ->with(['user:id,name,username', 'branch:id,name', 'processedBy:id,name'])
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
            'ID', 'Kode Internal', 'No. Permintaan', 'No. Jurnal', 'Tanggal Pengajuan',
            'Terakhir Diperbarui', 'User ID', 'Pemohon', 'Username Pemohon', 'Branch ID',
            'Cabang', 'Divisi', 'Nama Barang', 'Qty', 'Jenis Pembelian', 'Alasan Pembelian',
            'Link E-commerce', 'Lampiran', 'Status', 'Catatan Admin', 'Processed By ID', 'Diproses Oleh',
        ];
    }

    /** @return array<int, int|string> */
    private function row(PurchaseRequest $request): array
    {
        return [
            $request->id,
            $this->safeText($request->code),
            $this->safeText($request->purchase_request_number),
            $this->safeText($request->journal_item_number),
            $request->created_at?->format('Y-m-d H:i:s') ?? '',
            $request->updated_at?->format('Y-m-d H:i:s') ?? '',
            $request->user_id,
            $this->safeText($request->user?->name),
            $this->safeText($request->user?->username),
            $request->branch_id ?? '',
            $this->safeText($request->branch?->name),
            $this->safeText($request->division),
            $this->safeText($request->item_name),
            $request->quantity,
            $request->purchase_type instanceof PurchaseType ? $request->purchase_type->getLabel() : (string) $request->purchase_type,
            $this->safeText($request->purchase_reason),
            $this->safeText($request->ecommerce_link),
            $this->safeText(is_array($request->attachment_paths) ? implode(' | ', $request->attachment_paths) : (string) ($request->attachment_paths ?? '')),
            $request->status instanceof PurchaseRequestStatus ? $request->status->getLabel() : (string) $request->status,
            $this->safeText($request->admin_notes),
            $request->processed_by ?? '',
            $this->safeText($request->processedBy?->name),
        ];
    }

    private function filename(?string $dateFrom, ?string $dateUntil): string
    {
        $from = $dateFrom ?: 'semua';
        $until = $dateUntil ?: 'semua';

        return "permintaan-pembelian-{$from}-sampai-{$until}.xlsx";
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
