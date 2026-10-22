<?php

namespace App\Actions\SalesReport\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streaming XLSX writer shared by the Compliment and Void exports, following
 * ExportSalesReportsXlsxAction: rows are written chunk by chunk (never the whole dataset in
 * memory), text cells are protected from formula injection, and an empty result still yields a
 * valid header-only file.
 */
trait WritesSalesReportXlsx
{
    private const CHUNK_SIZE = 500;

    /**
     * @param  list<string>  $headers
     * @param  Closure(mixed): array<int, float|int|string>  $row
     */
    private function download(Builder $query, array $headers, Closure $row, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'sales_report_export_');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers, (new Style)->setFontBold()->setBackgroundColor('2563EB')->setFontColor(Color::WHITE)));

        // chunkById pages by primary key; a table sort order left on the query would skip or
        // repeat rows, so it is cleared first.
        $query->reorder()->chunkById(self::CHUNK_SIZE, function ($records) use ($writer, $row): void {
            foreach ($records as $record) {
                $writer->addRow(Row::fromValues($row($record)));
            }
        }, $query->getModel()->getQualifiedKeyName(), $query->getModel()->getKeyName());

        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Text cells stay strings (Sales Number leading zeros survive) and never start a formula.
     */
    private function safeText(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $text) === 1 ? "'{$text}" : $text;
    }

    private function filename(string $type, ?string $from, ?string $until): string
    {
        return sprintf('%s-%s-sampai-%s.xlsx', $type, $from ?: 'semua', $until ?: 'semua');
    }
}
