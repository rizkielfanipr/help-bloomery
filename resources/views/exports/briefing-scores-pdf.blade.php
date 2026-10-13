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
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #7c3aed; color: white; padding: 7px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; }
        td { padding: 6px 10px; border-bottom: 1px solid #f3f4f6; }
        tr:nth-child(even) td { background: #fafafa; }
        .pass { color: #059669; font-weight: bold; }
        .fail { color: #dc2626; font-weight: bold; }
        .score-pass { background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px; font-weight: bold; }
        .score-fail { background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-weight: bold; }
        .footer { font-size: 9px; color: #9ca3af; text-align: right; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Nilai Briefing</h1>
        <p>{{ $periodLabel }} &middot; Dicetak {{ now()->isoFormat('D MMMM Y HH:mm') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Branch</th>
                <th>Bulan</th>
                <th>Nilai</th>
                <th>Status</th>
                <th>Dihitung</th>
            </tr>
        </thead>
        <tbody>
            @foreach($scores as $i => $score)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $score->branch->name }}</td>
                    <td>{{ $score->monthLabel() }}</td>
                    <td>
                        <span class="{{ $score->isPassing() ? 'score-pass' : 'score-fail' }}">
                            {{ number_format($score->score, 2) }}%
                        </span>
                    </td>
                    <td class="{{ $score->isPassing() ? 'pass' : 'fail' }}">
                        {{ $score->isPassing() ? 'Achieve' : 'Tidak Achieve' }}
                    </td>
                    <td>{{ $score->computed_at?->format('d/m/Y H:i') ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Total: {{ $scores->count() }} branch &middot; Achieve: {{ $scores->filter(fn($s) => $s->isPassing())->count() }} &middot; Tidak Achieve: {{ $scores->filter(fn($s) => !$s->isPassing())->count() }}
    </div>
</body>
</html>
