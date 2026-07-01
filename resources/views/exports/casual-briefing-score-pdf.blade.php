<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        .header { background: #5b21b6; color: white; padding: 16px 20px; margin-bottom: 16px; }
        .header h1 { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        .header p { font-size: 10px; opacity: .8; }
        .score-card { border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; }
        .score-card.pass { background: #d1fae5; border: 1px solid #6ee7b7; }
        .score-card.fail { background: #fee2e2; border: 1px solid #fca5a5; }
        .score-card .label { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
        .score-card .value { font-size: 32px; font-weight: bold; }
        .score-card.pass .value { color: #065f46; }
        .score-card.fail .value { color: #991b1b; }
        .score-card .status { display: inline-block; font-size: 11px; font-weight: bold; padding: 3px 10px; border-radius: 20px; margin-top: 6px; }
        .score-card.pass .status { background: #059669; color: white; }
        .score-card.fail .status { background: #dc2626; color: white; }
        .section-title { font-size: 11px; font-weight: bold; color: #374151; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .05em; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #7c3aed; color: white; padding: 6px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .05em; }
        td { padding: 6px 10px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
        tr:nth-child(even) td { background: #fafafa; }
        td.score-val { font-weight: bold; text-align: right; }
        td.score-val.has { color: #059669; }
        td.score-val.none { color: #dc2626; }
        .total-row td { font-weight: bold; background: #ede9fe; border-top: 2px solid #7c3aed; }
        .footer { font-size: 9px; color: #9ca3af; text-align: right; margin-top: 12px; }
        .meta { font-size: 10px; color: #6b7280; margin-bottom: 16px; }
        .meta span { margin-right: 16px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Nilai Briefing</h1>
        <p>{{ $score->branch->name }} &middot; {{ $periodLabel }} &middot; Dicetak {{ now()->isoFormat('D MMMM Y HH:mm') }}</p>
    </div>

    <div class="score-card {{ $score->isPassing() ? 'pass' : 'fail' }}">
        <div class="label">Total Nilai</div>
        <div class="value">{{ number_format($score->score, 2) }}%</div>
        <div class="status">{{ $score->isPassing() ? '✓ Achieve' : '✗ Tidak Achieve' }}</div>
    </div>

    <div class="section-title">Rincian Per Poin</div>

    <table>
        <thead>
            <tr>
                <th>Poin</th>
                <th>Periode</th>
                <th>Terpenuhi</th>
                <th>Bobot</th>
                <th style="text-align:right">Nilai</th>
            </tr>
        </thead>
        <tbody>
            @php
                $periodLabels = ['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'];
            @endphp
            @foreach($score->breakdown ?? [] as $item)
                <tr>
                    <td>{{ $item['label'] }}</td>
                    <td>{{ $periodLabels[$item['period']] ?? $item['period'] }}</td>
                    <td>{{ $item['approved'] }}/{{ $item['expected'] }}</td>
                    <td>{{ $item['weight'] }}%</td>
                    <td class="score-val {{ $item['score'] > 0 ? 'has' : 'none' }}">{{ number_format($item['score'], 2) }}%</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4">Total</td>
                <td class="score-val {{ $score->isPassing() ? 'has' : 'none' }}">{{ number_format($score->score, 2) }}%</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Dihitung: {{ $score->computed_at?->isoFormat('D MMMM Y HH:mm') ?? '-' }} &middot; Minimum lulus: 80%
    </div>
</body>
</html>
