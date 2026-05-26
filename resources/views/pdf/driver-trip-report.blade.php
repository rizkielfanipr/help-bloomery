<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Perjalanan Driver</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }

        .header { background: #1d4ed8; color: white; padding: 16px 20px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; font-weight: bold; }
        .header p { font-size: 11px; opacity: 0.9; margin-top: 4px; }

        .info-grid { display: table; width: 100%; margin-bottom: 16px; }
        .info-row { display: table-row; }
        .info-cell { display: table-cell; padding: 4px 8px; }
        .info-label { font-weight: bold; color: #555; width: 120px; }

        .summary-grid { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .summary-grid td { padding: 10px 14px; text-align: center; border: 1px solid #e2e8f0; }
        .summary-title { font-size: 10px; color: #666; }
        .summary-value { font-size: 15px; font-weight: bold; margin-top: 3px; }
        .summary-value.green { color: #16a34a; }
        .summary-value.blue { color: #2563eb; }

        .section-title { font-size: 12px; font-weight: bold; background: #f1f5f9; padding: 6px 10px;
                          border-left: 3px solid #3b82f6; margin-bottom: 8px; }

        .trip-card { border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 12px; page-break-inside: avoid; }
        .trip-header { background: #f8fafc; padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
        .trip-header .route { font-weight: bold; font-size: 12px; }
        .trip-header .meta { font-size: 10px; color: #666; margin-top: 2px; }
        .trip-body { padding: 10px 12px; }

        .waypoints { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .waypoints th { background: #f8fafc; padding: 5px 8px; text-align: left; font-size: 10px;
                         color: #555; border: 1px solid #e2e8f0; }
        .waypoints td { padding: 5px 8px; border: 1px solid #e2e8f0; font-size: 10px; vertical-align: top; }

        .fuel-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 3px; padding: 8px; margin-top: 8px; }
        .fuel-box .fuel-title { font-weight: bold; font-size: 10px; color: #92400e; margin-bottom: 4px; }
        .fuel-grid { width: 100%; border-collapse: collapse; }
        .fuel-grid td { padding: 3px 6px; font-size: 10px; }
        .fuel-grid .label { color: #666; width: 110px; }

        .badge { display: inline-block; padding: 1px 6px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-warning { background: #fef9c3; color: #854d0e; }

        .allowance { color: #15803d; font-weight: bold; }

        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #e2e8f0;
                   text-align: center; font-size: 9px; color: #999; }

        .no-trips { text-align: center; padding: 30px; color: #999; }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <h1>Laporan Perjalanan Driver</h1>
        <p>Periode: {{ $periodStart->format('d M Y') }} — {{ $periodEnd->format('d M Y') }}</p>
    </div>

    {{-- Driver Info --}}
    <div class="info-grid">
        <div class="info-row">
            <div class="info-cell info-label">Nama Driver</div>
            <div class="info-cell">: {{ $driver->name }}</div>
            <div class="info-cell info-label">Periode</div>
            <div class="info-cell">: {{ $monthName }} {{ $year }}</div>
        </div>
        <div class="info-row">
            <div class="info-cell info-label">Email</div>
            <div class="info-cell">: {{ $driver->email }}</div>
            <div class="info-cell info-label">Tanggal Cetak</div>
            <div class="info-cell">: {{ now()->format('d M Y H:i') }}</div>
        </div>
    </div>

    {{-- Summary --}}
    <table class="summary-grid">
        <tr>
            <td>
                <div class="summary-title">Total Perjalanan</div>
                <div class="summary-value">{{ $trips->count() }}</div>
            </td>
            <td>
                <div class="summary-title">Total Uang Makan</div>
                <div class="summary-value green">Rp {{ number_format($totalMealAllowance, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="summary-title">Total Biaya BBM</div>
                <div class="summary-value blue">Rp {{ number_format($totalFuelCost, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    {{-- Trip Details --}}
    <div class="section-title">Detail Perjalanan</div>

    @forelse($trips as $trip)
        <div class="trip-card">
            <div class="trip-header">
                <div class="route">{{ $trip->tripRoute->name }}</div>
                <div class="meta">
                    {{ $trip->trip_date->format('d M Y') }} &bull;
                    {{ $trip->vehicle->license_plate ?? '-' }} &bull;
                    Selesai: {{ $trip->completed_at?->format('H:i') ?? '-' }}
                    @if($trip->meal_allowance_amount)
                        &bull; <span class="allowance">Uang Makan: Rp {{ number_format($trip->meal_allowance_amount, 0, ',', '.') }}</span>
                    @endif
                </div>
            </div>

            <div class="trip-body">
                {{-- Waypoints --}}
                @if($trip->waypointCheckins->isNotEmpty())
                    <table class="waypoints">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Titik</th>
                                <th>Waktu Check-in</th>
                                <th>Status</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trip->waypointCheckins->sortBy('waypoint.urutan') as $checkin)
                                <tr>
                                    <td>{{ $checkin->waypoint->urutan }}</td>
                                    <td>{{ $checkin->waypoint->name }}</td>
                                    <td>{{ $checkin->checked_in_at?->format('H:i') ?? '-' }}</td>
                                    <td>
                                        @if($checkin->checked_in_at)
                                            <span class="badge badge-success">Selesai</span>
                                        @else
                                            <span class="badge badge-warning">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $checkin->notes ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                {{-- Fuel Info --}}
                @if($trip->has_fuel_fillup && $trip->fuelFillup)
                    <div class="fuel-box">
                        <div class="fuel-title">⛽ Pengisian BBM</div>
                        <table class="fuel-grid">
                            <tr>
                                <td class="label">Alamat SPBU</td>
                                <td>: {{ $trip->fuelFillup->spbu_address }}</td>
                                <td class="label">Jenis BBM</td>
                                <td>: {{ $trip->fuelFillup->fuel_type }}</td>
                            </tr>
                            <tr>
                                <td class="label">Jumlah</td>
                                <td>: {{ $trip->fuelFillup->liters }} Liter</td>
                                <td class="label">Harga/Liter</td>
                                <td>: Rp {{ number_format($trip->fuelFillup->price_per_liter, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="label">Total Harga</td>
                                <td colspan="3">: <strong>Rp {{ number_format($trip->fuelFillup->total_price, 0, ',', '.') }}</strong></td>
                            </tr>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="no-trips">Tidak ada perjalanan di periode ini</div>
    @endforelse

    {{-- Footer --}}
    <div class="footer">
        <p>Dokumen ini digenerate otomatis oleh sistem • {{ config('app.name') }}</p>
    </div>

</body>
</html>
