<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; background: #fff; }
    .page { padding: 28px 32px 24px; }

    /* ── Header ── */
    .header-bar {
        background: #1d4ed8;
        border-radius: 10px;
        padding: 18px 20px;
        margin-bottom: 18px;
    }
    .header-logo { width: 40px; height: 40px; border-radius: 8px; vertical-align: middle; }
    .header-brand { display: inline-block; vertical-align: middle; margin-left: 12px; }
    .header-brand-name { font-size: 15px; font-weight: 700; color: #fff; letter-spacing: 0.3px; }
    .header-brand-sub { font-size: 9px; color: #bfdbfe; margin-top: 1px; }
    .header-meta { float: right; text-align: right; padding-top: 4px; }
    .header-doc-title { font-size: 13px; font-weight: 700; color: #fff; }
    .header-doc-period { font-size: 9px; color: #bfdbfe; margin-top: 3px; }
    .header-generated { font-size: 8px; color: #93c5fd; margin-top: 2px; }

    /* ── Employee Card ── */
    .employee-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 16px;
        background: #f8fafc;
    }
    .employee-card-header {
        font-size: 9px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 10px;
        padding-bottom: 6px;
        border-bottom: 1px solid #e2e8f0;
    }
    .employee-grid { width: 100%; border-collapse: collapse; }
    .employee-grid td { padding: 3px 0; vertical-align: top; }
    .emp-label { color: #94a3b8; font-size: 9px; width: 90px; }
    .emp-value { font-weight: 700; font-size: 10px; color: #1e293b; }
    .emp-value-secondary { font-size: 10px; color: #475569; }

    /* ── Stat Cards ── */
    .stats-section { margin-bottom: 16px; }
    .section-title {
        font-size: 10px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        margin-bottom: 10px;
        padding-left: 10px;
        border-left: 3px solid #1d4ed8;
    }
    .stat-cards { width: 100%; border-collapse: separate; border-spacing: 6px; }
    .stat-card {
        border-radius: 8px;
        padding: 10px 12px;
        text-align: center;
        border: 1px solid;
        width: 16.66%;
    }
    .stat-num { font-size: 22px; font-weight: 800; display: block; line-height: 1.1; }
    .stat-lbl { font-size: 8px; font-weight: 600; letter-spacing: 0.3px; display: block; margin-top: 3px; text-transform: uppercase; }

    .card-blue   { background: #eff6ff; border-color: #bfdbfe; }
    .card-blue   .stat-num { color: #1d4ed8; }
    .card-blue   .stat-lbl { color: #3b82f6; }

    .card-green  { background: #f0fdf4; border-color: #bbf7d0; }
    .card-green  .stat-num { color: #15803d; }
    .card-green  .stat-lbl { color: #22c55e; }

    .card-red    { background: #fef2f2; border-color: #fecaca; }
    .card-red    .stat-num { color: #b91c1c; }
    .card-red    .stat-lbl { color: #ef4444; }

    .card-amber  { background: #fffbeb; border-color: #fde68a; }
    .card-amber  .stat-num { color: #b45309; }
    .card-amber  .stat-lbl { color: #f59e0b; }

    .card-teal   { background: #f0fdfa; border-color: #99f6e4; }
    .card-teal   .stat-num { color: #0f766e; }
    .card-teal   .stat-lbl { color: #14b8a6; }

    .card-purple { background: #faf5ff; border-color: #e9d5ff; }
    .card-purple .stat-num { color: #6d28d9; }
    .card-purple .stat-lbl { color: #8b5cf6; }

    /* ── Rate Bars ── */
    .rate-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .rate-table td { padding: 0 8px; vertical-align: top; }
    .rate-table td:first-child { padding-left: 0; }
    .rate-table td:last-child { padding-right: 0; }
    .rate-block { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; }
    .rate-head { font-size: 9px; color: #64748b; font-weight: 600; margin-bottom: 6px; }
    .rate-pct { font-size: 18px; font-weight: 800; }
    .rate-bar-bg { background: #e2e8f0; border-radius: 4px; height: 6px; margin-top: 6px; }
    .rate-bar-fill { height: 6px; border-radius: 4px; }
    .rate-sub { font-size: 9px; color: #94a3b8; margin-top: 4px; }

    /* ── Records Table ── */
    .records-table { width: 100%; border-collapse: collapse; font-size: 9.5px; }
    .records-table thead tr { background: #1d4ed8; }
    .records-table th {
        color: #fff;
        padding: 7px 8px;
        text-align: left;
        font-size: 8.5px;
        font-weight: 700;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }
    .records-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .records-table tr:last-child td { border-bottom: none; }
    .records-table tr:nth-child(even) td { background: #f8fafc; }
    .records-wrap { border: 1px solid #e2e8f0; border-radius: 8px; }

    .badge {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 20px;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.2px;
    }
    .badge-green  { background: #dcfce7; color: #15803d; }
    .badge-red    { background: #fee2e2; color: #b91c1c; }
    .badge-amber  { background: #fef3c7; color: #b45309; }
    .badge-blue   { background: #dbeafe; color: #1d4ed8; }
    .badge-purple { background: #ede9fe; color: #6d28d9; }

    .text-center { text-align: center; }
    .text-muted  { color: #94a3b8; }
    .text-right  { text-align: right; }

    /* ── OT Summary ── */
    .ot-summary {
        margin-top: 10px;
        background: #f5f3ff;
        border: 1px solid #ddd6fe;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 9.5px;
        color: #5b21b6;
        font-weight: 700;
    }
    .ot-summary span { color: #7c3aed; }

    /* ── Hour Summary Bar ── */
    .hour-bar { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; }
    .hour-bar table { width: 100%; border-collapse: collapse; }
    .hour-bar td { padding: 0 10px; border-right: 1px solid #e2e8f0; text-align: center; vertical-align: middle; }
    .hour-bar td:first-child { padding-left: 0; }
    .hour-bar td:last-child  { border-right: none; padding-right: 0; }
    .hour-label { font-size: 8.5px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; display: block; margin-bottom: 2px; }
    .hour-value { font-size: 14px; font-weight: 800; }

    /* ── Footer ── */
    .footer {
        margin-top: 20px;
        padding-top: 10px;
        border-top: 1px solid #e2e8f0;
        font-size: 8px;
        color: #94a3b8;
        text-align: center;
    }
    .footer-logo { color: #1d4ed8; font-weight: 700; }
</style>
</head>
<body>
<div class="page">

    {{-- ── Header ── --}}
    <div class="header-bar">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="vertical-align:middle; width:60%;">
                    <img src="{{ public_path('images/bloomery-icon.png') }}" class="header-logo" alt="Bloomery">
                    <span class="header-brand">
                        <div class="header-brand-name">Help Bloomery</div>
                        <div class="header-brand-sub">Casual Staff Management System</div>
                    </span>
                </td>
                <td class="header-meta" style="vertical-align:middle;">
                    <div class="header-doc-title">Laporan Kinerja Karyawan</div>
                    <div class="header-doc-period">{{ $weekStart->format('d/m/Y') }} &mdash; {{ $weekEnd->format('d/m/Y') }}</div>
                    <div class="header-generated">Digenerate: {{ now()->format('d M Y, H:i') }} WIB</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Employee Info ── --}}
    <div class="employee-card">
        <div class="employee-card-header">Informasi Karyawan</div>
        <table class="employee-grid">
            <tr>
                <td class="emp-label">Nama</td>
                <td class="emp-value" style="width:160px">{{ $user->name }}</td>
                <td style="width:24px"></td>
                <td class="emp-label">Jabatan</td>
                <td class="emp-value">{{ $position?->name ?? 'Casual Staff' }}</td>
            </tr>
            <tr>
                <td class="emp-label">Email</td>
                <td class="emp-value-secondary">{{ $user->email }}</td>
                <td></td>
                <td class="emp-label">Shift</td>
                <td class="emp-value-secondary">
                    {{ $shift?->name ?? '–' }}
                    @if($shift)
                        &nbsp;({{ \Carbon\Carbon::parse($shift->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($shift->end_time)->format('H:i') }})
                    @endif
                </td>
            </tr>
            <tr>
                <td class="emp-label">ID Karyawan</td>
                <td class="emp-value-secondary">#{{ str_pad($user->id, 5, '0', STR_PAD_LEFT) }}</td>
                <td></td>
                <td class="emp-label">Periode</td>
                <td class="emp-value-secondary">{{ $weekStart->format('d/m/Y') }} – {{ $weekEnd->format('d/m/Y') }}</td>
            </tr>
        </table>
    </div>

    {{-- ── Attendance Stats ── --}}
    <div class="stats-section">
        <div class="section-title">Ringkasan Kehadiran</div>
        <table class="stat-cards">
            <tr>
                <td class="stat-card card-blue">
                    <span class="stat-num">{{ $totalDays }}</span>
                    <span class="stat-lbl">Total Hari</span>
                </td>
                <td class="stat-card card-green">
                    <span class="stat-num">{{ $presentCount }}</span>
                    <span class="stat-lbl">Hadir</span>
                </td>
                <td class="stat-card card-red">
                    <span class="stat-num">{{ $absentCount }}</span>
                    <span class="stat-lbl">Absen</span>
                </td>
                <td class="stat-card card-amber">
                    <span class="stat-num">{{ $lateCount }}</span>
                    <span class="stat-lbl">Terlambat</span>
                </td>
                <td class="stat-card card-teal">
                    <span class="stat-num">{{ $onTimeCount }}</span>
                    <span class="stat-lbl">Tepat Waktu</span>
                </td>
                <td class="stat-card card-purple">
                    <span class="stat-num">{{ $earlyOutCount }}</span>
                    <span class="stat-lbl">Keluar Awal</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Rates + Hours ── --}}
    @php
        $totalH = intdiv($totalSeconds, 3600);
        $totalM = intdiv($totalSeconds % 3600, 60);
        $avgH   = intdiv($avgSeconds, 3600);
        $avgM   = intdiv($avgSeconds % 3600, 60);
        $attendColor = $attendanceRate >= 80 ? '#15803d' : ($attendanceRate >= 60 ? '#b45309' : '#b91c1c');
        $punctColor  = $punctualityRate >= 80 ? '#15803d' : ($punctualityRate >= 60 ? '#b45309' : '#b91c1c');
        $attendBg    = $attendanceRate >= 80 ? '#16a34a' : ($attendanceRate >= 60 ? '#d97706' : '#dc2626');
        $punctBg     = $punctualityRate >= 80 ? '#16a34a' : ($punctualityRate >= 60 ? '#d97706' : '#dc2626');
    @endphp

    <table class="rate-table" style="margin-bottom:16px;">
        <tr>
            <td style="width:33%; padding-left:0; padding-right:5px;">
                <div class="rate-block">
                    <div class="rate-head">Tingkat Kehadiran</div>
                    <div class="rate-pct" style="color:{{ $attendColor }}">{{ $attendanceRate }}%</div>
                    <div class="rate-bar-bg"><div class="rate-bar-fill" style="width:{{ $attendanceRate }}%; background:{{ $attendBg }};"></div></div>
                    <div class="rate-sub">{{ $presentCount }} dari {{ $totalDays }} hari</div>
                </div>
            </td>
            <td style="width:33%; padding:0 5px;">
                <div class="rate-block">
                    <div class="rate-head">Ketepatan Waktu</div>
                    <div class="rate-pct" style="color:{{ $punctColor }}">{{ $punctualityRate }}%</div>
                    <div class="rate-bar-bg"><div class="rate-bar-fill" style="width:{{ $punctualityRate }}%; background:{{ $punctBg }};"></div></div>
                    <div class="rate-sub">{{ $onTimeCount }} dari {{ $presentCount }} hari hadir</div>
                </div>
            </td>
            <td style="width:33%; padding-left:5px; padding-right:0;">
                <div class="rate-block">
                    <div class="rate-head">Jam Kerja</div>
                    <div class="rate-pct" style="color:#6d28d9">{{ $totalH }}j {{ $totalM }}m</div>
                    <div class="rate-bar-bg" style="background:#e9d5ff;"><div class="rate-bar-fill" style="width:100%; background:#8b5cf6;"></div></div>
                    <div class="rate-sub">Rata-rata {{ $avgH }}j {{ $avgM }}m / hari</div>
                </div>
            </td>
        </tr>
    </table>

    @if($overtimeCount > 0)
        @php
            $otTotalMins = (int) round($overtimeTotalHours * 60);
            $otTH = intdiv($otTotalMins, 60); $otTM = $otTotalMins % 60;
            $otLabel = $otTH > 0 ? ($otTM > 0 ? "{$otTH}j {$otTM}m" : "{$otTH}j") : "{$otTM}m";
        @endphp
        <div style="margin-bottom:16px;">
            <div class="section-title">Lembur</div>
            <table style="width:100%; border-collapse:separate; border-spacing:6px;">
                <tr>
                    <td class="stat-card card-purple" style="width:33%">
                        <span class="stat-num">{{ $overtimeCount }}</span>
                        <span class="stat-lbl">Sesi Lembur</span>
                    </td>
                    <td class="stat-card card-purple" style="width:33%">
                        <span class="stat-num">{{ $otLabel }}</span>
                        <span class="stat-lbl">Total Durasi</span>
                    </td>
                    <td class="stat-card card-purple" style="width:33%">
                        <span class="stat-num" style="font-size:14px;">Rp {{ number_format($overtimeTotalFee, 0, ',', '.') }}</span>
                        <span class="stat-lbl">Total Fee Lembur</span>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    {{-- ── Daily Records ── --}}
    <div class="section-title">Rincian Harian</div>

    @if($records->isEmpty())
        <div style="padding:20px; text-align:center; color:#94a3b8; font-size:11px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
            Tidak ada data absensi untuk periode ini.
        </div>
    @else
        <div class="records-wrap">
            <table class="records-table">
                <thead>
                    <tr>
                        <th style="width:24px">#</th>
                        <th>Tanggal</th>
                        <th>Masuk</th>
                        <th>Keluar</th>
                        <th>Durasi</th>
                        <th>Status Masuk</th>
                        <th>Status Pulang</th>
                        <th class="text-center">Lembur</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $i => $rec)
                        @php
                            $durSec = $rec->clock_in_at && $rec->clock_out_at
                                ? (int) $rec->clock_in_at->diffInSeconds($rec->clock_out_at) : 0;
                            $durH = intdiv($durSec, 3600);
                            $durM = intdiv($durSec % 3600, 60);
                            $otReq = $rec->overtimeRequest;
                        @endphp
                        <tr>
                            <td class="text-center text-muted" style="font-size:8.5px;">{{ $i + 1 }}</td>
                            <td>
                                <strong style="font-size:10px;">{{ $rec->date->format('d') }}</strong>
                                <span style="color:#64748b;">{{ ['Min','Sen','Sel','Rab','Kam','Jum','Sab'][$rec->date->dayOfWeek] }},
                                {{ ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'][$rec->date->month - 1] }}</span>
                            </td>
                            <td style="font-weight:600; color:#15803d;">{{ $rec->clock_in_at?->format('H:i') ?? '—' }}</td>
                            <td style="font-weight:600; color:#b91c1c;">{{ $rec->clock_out_at?->format('H:i') ?? '—' }}</td>
                            <td style="font-weight:600;">{{ $durSec > 0 ? "{$durH}j {$durM}m" : '—' }}</td>
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
                            <td class="text-center">
                                @if($otReq)
                                    @php
                                        $otMins = (int) round($otReq->approved_hours * 60);
                                        $otH = intdiv($otMins, 60); $otM = $otMins % 60;
                                    @endphp
                                    <span class="badge badge-purple">{{ $otH > 0 ? "{$otH}j {$otM}m" : "{$otM}m" }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($overtimeCount > 0)
            <div class="ot-summary">
                Total Lembur:
                <span>{{ $overtimeCount }} sesi</span> &bull;
                <span>{{ $otLabel }}</span> &bull;
                <span>Rp {{ number_format($overtimeTotalFee, 0, ',', '.') }}</span>
            </div>
        @endif
    @endif

    {{-- ── Footer ── --}}
    <div class="footer">
        <span class="footer-logo">Help Bloomery</span> &mdash;
        Dokumen ini digenerate secara otomatis pada {{ now()->format('d F Y \p\u\k\u\l H:i') }} WIB.
        Laporan ini bersifat resmi dan tidak memerlukan tanda tangan.
    </div>

</div>
</body>
</html>
