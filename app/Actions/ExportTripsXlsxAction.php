<?php

namespace App\Actions;

use App\Enums\TripStatus;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportTripsXlsxAction
{
    public function execute(Builder $query, ?string $dateFrom = null, ?string $dateUntil = null): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'trips_export_');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers(), $this->headerStyle()));

        $query
            ->with(['driver:id,name,username', 'vehicle', 'tripRoute', 'fuelFillup'])
            ->when($dateFrom, fn (Builder $query, string $date): Builder => $query->whereDate('trip_date', '>=', $date))
            ->when($dateUntil, fn (Builder $query, string $date): Builder => $query->whereDate('trip_date', '<=', $date))
            ->chunkById(500, function ($trips) use ($writer): void {
                foreach ($trips as $trip) {
                    $writer->addRow(Row::fromValues($this->row($trip)));
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
            'ID', 'Kode Perjalanan', 'Tanggal', 'Driver', 'Kendaraan', 'Rute',
            'Status', 'Waktu Mulai', 'Waktu Selesai', 'Odometer Awal (KM)',
            'Odometer Akhir (KM)', 'Total Jarak (KM)', 'Pengisian BBM',
            'Total Bayar BBM (Rp)', 'Uang Makan (Rp)', 'Catatan',
        ];
    }

    /** @return array<int, int|float|string> */
    private function row(Trip $trip): array
    {
        $vehicleInfo = trim(($trip->vehicle?->license_plate ?? '').' '.($trip->vehicle?->brand ?? '').' '.($trip->vehicle?->model ?? ''));
        $totalDistance = ($trip->odo_start !== null && $trip->odo_end !== null) ? max(0, $trip->odo_end - $trip->odo_start) : '';

        return [
            $trip->id,
            $this->safeText($trip->code ?: 'TRIP-'.$trip->id),
            $trip->trip_date?->format('Y-m-d') ?? '',
            $this->safeText($trip->driver?->name),
            $this->safeText($vehicleInfo ?: '-'),
            $this->safeText($trip->tripRoute?->name),
            $trip->status instanceof TripStatus ? $trip->status->getLabel() : (string) $trip->status,
            $trip->started_at?->format('Y-m-d H:i:s') ?? '',
            $trip->completed_at?->format('Y-m-d H:i:s') ?? '',
            $trip->odo_start ?? '',
            $trip->odo_end ?? '',
            $totalDistance,
            $trip->fuelFillup ? $trip->fuelFillup->fuel_type.' ('.$trip->fuelFillup->liters.' L)' : ($trip->has_fuel_fillup ? 'Tercatat' : 'Tanpa BBM'),
            $trip->fuelFillup ? (float) $trip->fuelFillup->total_price : 0,
            (float) ($trip->meal_allowance_amount ?? 0),
            $this->safeText($trip->notes),
        ];
    }

    private function filename(?string $dateFrom, ?string $dateUntil): string
    {
        $from = $dateFrom ?: 'semua';
        $until = $dateUntil ?: 'semua';

        return "monitoring-perjalanan-driver-{$from}-sampai-{$until}.xlsx";
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
