<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1f2937; background: #fff; }
    .page { padding: 32px 36px; }

    /* Header */
    .header { border-bottom: 2px solid #2563eb; padding-bottom: 14px; margin-bottom: 20px; }
    .header-title { font-size: 17px; font-weight: 700; color: #2563eb; letter-spacing: 0.5px; }
    .header-sub { font-size: 10px; color: #6b7280; margin-top: 2px; }
    .header-right { text-align: right; font-size: 10px; color: #6b7280; }

    /* Info table */
    .info-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .info-table td { padding: 5px 8px; }
    .info-table tr:nth-child(odd) td { background: #f8fafc; }
    .info-label { color: #6b7280; font-size: 10px; width: 120px; }
    .info-value { font-weight: 600; }

    /* Stats grid */
    .stats-grid { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .stats-grid td { padding: 10px 12px; text-align: center; border: 1px solid #e5e7eb; }
    .stat-num { font-size: 20px; font-weight: 800; color: #1f2937; display: block; }
    .stat-label { font-size: 9px; color: #9ca3af; margin-top: 2px; display: block; }
    .stat-green .stat-num { color: #16a34a; }
    .stat-red   .stat-num { color: #dc2626; }
    .stat-amber .stat-num { color: #d97706; }
    .stat-blue  .stat-num { color: #2563eb; }
    .stat-purple .stat-num { color: #7c3aed; }

    /* Rate bar */
    .rate-row { margin-bottom: 18px; }
    .rate-bar-bg { background: #e5e7eb; border-radius: 4px; height: 8px; width: 100%; }
    .rate-bar-fill { height: 8px; border-radius: 4px; }
    .rate-label { font-size: 10px; color: #6b7280; margin-bottom: 4px; }
    .rate-pct { font-size: 12px; font-weight: 700; float: right; }

    /* Records table */
    .section-title { font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 8px; border-left: 3px solid #2563eb; padding-left: 8px; }
    .records-table { width: 100%; border-collapse: collapse; font-size: 10px; }
    .records-table th { background: #2563eb; color: #fff; padding: 7px 8px; text-align: left; font-weight: 600; }
    .records-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .records-table tr:nth-child(even) td { background: #f8fafc; }
    .badge { display: inline-block; padding: 2px 6px; border-radius: 20px; font-size: 9px; font-weight: 600; }
    .badge-green  { background: #dcfce7; color: #16a34a; }
    .badge-red    { background: #fee2e2; color: #dc2626; }
    .badge-amber  { background: #fef3c7; color: #d97706; }
    .badge-blue   { background: #dbeafe; color: #2563eb; }
    .text-center  { text-align: center; }
    .text-muted   { color: #9ca3af; }

    /* Footer */
    .footer { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <table style="width:100%; margin-bottom:0">
        <tr>
            <td>
                <div class="header-title">LAPORAN KINERJA KARYAWAN</div>
                <div class="header-sub">Help Bloomery &mdash; Casual Staff Panel</div>
            </td>
            <td class="header-right">
                Periode: <strong>{{ $month->locale('id')->isoFormat('MMMM YYYY') }}</strong><br>
                Digenerate: {{ now()->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>
    <div style="border-bottom: 2px solid #2563eb; margin-bottom: 18px; margin-top: 10px;"></div>

    {{-- User Info --}}
    <table class="info-table">
        <tr>
            <td class="info-label">Nama</td>
            <td class="info-value">{{ $user->name }}</td>
            <td class="info-label">Periode</td>
            <td class="info-value">{{ $start->format('d/m/Y') }} &ndash; {{ $end->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="info-label">Jabatan</td>
            <td class="info-value">{{ $position?->name ?? 'Casual Staff' }}</td>
            <td class="info-label">Shift</td>
            <td class="info-value">{{ $shift?->name ?? '-' }}
                @if($shift) ({{ \Carbon\Carbon::parse($shift->start_time)->format('H:i') }}&ndash;{{ \Carbon\Carbon::parse($shift->end_time)->format('H:i') }}) @endif
            </td>
        </tr>
        <tr>
            <td class="info-label">Email</td>
            <td class="info-value">{{ $user->email }}</td>
            <td class="info-label">ID Karyawan</td>
            <td class="info-value">#{{ str_pad($user->id, 5, '0', STR_PAD_LEFT) }}</td>
        </tr>
    </table>

    {{-- Summary Stats --}}
    <div class="section-title">Ringkasan</div>
    <table class="stats-grid" style="margin-bottom: 14px;">
        <tr>
            <td class="stat-blue">
                <span class="stat-num">{{ $workingDays }}</span>
                <span class="stat-label">Hari Kerja</span>
            </td>
            <td class="stat-green">
                <span class="stat-num">{{ $presentCount }}</span>
                <span class="stat-label">Hadir</span>
            </td>
            <td class="stat-red">
                <span class="stat-num">{{ $absentCount }}</span>
                <span class="stat-label">Absen</span>
            </td>
            <td class="stat-amber">
                <span class="stat-num">{{ $lateCount }}</span>
                <span class="stat-label">Terlambat</span>
            </td>
            <td class="stat-green">
                <span class="stat-num">{{ $onTimeCount }}</span>
                <span class="stat-label">Tepat Waktu</span>
            </td>
            <td>
                <span class="stat-num" style="color:#7c3aed">{{ $earlyOutCount }}</span>
                <span class="stat-label">Keluar Awal</span>
            </td>
        </tr>
    </table>

    {{-- Rates --}}
    @php
        $totalH = intdiv($totalSeconds, 3600);
        $totalM = intdiv($totalSeconds % 3600, 60);
        $avgH   = intdiv($avgSeconds, 3600);
        $avgM   = intdiv($avgSeconds % 3600, 60);
        $attendColor = $attendanceRate >= 80 ? '#16a34a' : ($attendanceRate >= 60 ? '#d97706' : '#dc2626');
        $punctColor  = $punctualityRate >= 80 ? '#16a34a' : ($punctualityRate >= 60 ? '#d97706' : '#dc2626');
    @endphp
    <table style="width:100%; border-collapse:collapse; margin-bottom:18px;">
        <tr>
            <td style="width:50%; padding-right:10px;">
                <div class="rate-label">Tingkat Kehadiran <span class="rate-pct" style="color:{{ $attendColor }}">{{ $attendanceRate }}%</span></div>
                <div style="clear:both"></div>
                <div class="rate-bar-bg"><div class="rate-bar-fill" style="width:{{ $attendanceRate }}%; background:{{ $attendColor }};"></div></div>
            </td>
            <td style="width:50%; padding-left:10px;">
                <div class="rate-label">Tingkat Ketepatan Waktu <span class="rate-pct" style="color:{{ $punctColor }}">{{ $punctualityRate }}%</span></div>
                <div style="clear:both"></div>
                <div class="rate-bar-bg"><div class="rate-bar-fill" style="width:{{ $punctualityRate }}%; background:{{ $punctColor }};"></div></div>
            </td>
        </tr>
        <tr>
            <td style="padding-right:10px; padding-top:8px; font-size:10px;">
                <strong>Total Jam Kerja:</strong>
                <span style="color:#7c3aed; font-weight:700;">{{ $totalH }}j {{ $totalM }}m</span>
            </td>
            <td style="padding-left:10px; padding-top:8px; font-size:10px;">
                <strong>Rata-rata / Hari:</strong>
                <span style="color:#0891b2; font-weight:700;">{{ $avgH }}j {{ $avgM }}m</span>
            </td>
        </tr>
    </table>

    {{-- Detail Records --}}
    <div class="section-title">Rincian Harian</div>
    @if($records->isEmpty())
        <p style="color:#9ca3af; font-size:11px; padding:12px 0;">Tidak ada data absensi untuk periode ini.</p>
    @else
        <table class="records-table">
            <thead>
                <tr>
                    <th style="width:28px">#</th>
                    <th>Tanggal</th>
                    <th>Jam Masuk</th>
                    <th>Jam Keluar</th>
                    <th>Durasi</th>
                    <th>Status Masuk</th>
                    <th>Status Pulang</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $i => $rec)
                    @php
                        $durSec = $rec->clock_in_at && $rec->clock_out_at
                            ? (int) $rec->clock_in_at->diffInSeconds($rec->clock_out_at) : 0;
                        $durH = intdiv($durSec, 3600);
                        $durM = intdiv($durSec % 3600, 60);
                    @endphp
                    <tr>
                        <td class="text-center text-muted">{{ $i + 1 }}</td>
                        <td>
                            <strong>{{ $rec->date->format('d') }}</strong>
                            {{ ['Min','Sen','Sel','Rab','Kam','Jum','Sab'][$rec->date->dayOfWeek] }},
                            {{ ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'][$rec->date->month - 1] }}
                        </td>
                        <td>{{ $rec->clock_in_at?->format('H:i') ?? '—' }}</td>
                        <td>{{ $rec->clock_out_at?->format('H:i') ?? '—' }}</td>
                        <td>{{ $durSec > 0 ? "{$durH}j {$durM}m" : '—' }}</td>
                        <td>
                            @if($rec->is_late)
                                <span class="badge badge-red">Telat {{ $rec->late_minutes }}m</span>
                            @else
                                <span class="badge badge-green">Tepat Waktu</span>
                            @endif
                        </td>
                        <td>
                            @if(!$rec->clock_out_at)
                                <span class="badge badge-blue">Belum Keluar</span>
                            @elseif($rec->is_early_out)
                                <span class="badge badge-amber">Awal {{ $rec->early_out_minutes }}m</span>
                            @else
                                <span class="badge badge-green">Normal</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Footer --}}
    <div class="footer">
        Dokumen ini digenerate secara otomatis oleh sistem Help Bloomery pada {{ now()->format('d F Y \p\u\k\u\l H:i') }} WIB.
        Laporan ini bersifat resmi dan tidak memerlukan tanda tangan.
    </div>

</div>
</body>
</html>
