@php
    $breakdown = $record->breakdown ?? [];
    $periodLabels = ['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'];
    $periodColors = [
        'daily'   => 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
        'weekly'  => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'monthly' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
    ];
@endphp

<div class="space-y-4 py-2">

    {{-- Score summary --}}
    <div class="flex items-center gap-4 rounded-xl p-4 {{ $record->isPassing() ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-red-50 dark:bg-red-500/10' }}">
        <div class="flex-1">
            <p class="text-sm font-medium {{ $record->isPassing() ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                Total Nilai
            </p>
            <p class="text-3xl font-bold {{ $record->isPassing() ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                {{ number_format($record->score, 2) }}%
            </p>
        </div>
        <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $record->isPassing() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400' }}">
            {{ $record->isPassing() ? 'Achieve' : 'Tidak Achieve' }}
        </span>
    </div>

    {{-- Breakdown table --}}
    @if(count($breakdown))
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/[0.06] bg-gray-50 dark:bg-gray-800/50">
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Poin</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Periode</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Disetujui</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bobot</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-white/[0.04]">
                    @foreach($breakdown as $item)
                        <tr class="hover:bg-gray-50/60 dark:hover:bg-white/[0.02]">
                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white">{{ $item['label'] }}</td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $periodColors[$item['period']] ?? '' }}">
                                    {{ $periodLabels[$item['period']] ?? $item['period'] }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-center text-xs text-gray-500 dark:text-gray-400">
                                {{ $item['approved'] }}/{{ $item['expected'] }}
                            </td>
                            <td class="px-3 py-2.5 text-center text-xs text-gray-500 dark:text-gray-400">{{ $item['weight'] }}%</td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="font-semibold {{ $item['score'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                                    {{ number_format($item['score'], 2) }}%
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-800/50">
                        <td colspan="4" class="px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-200">Total</td>
                        <td class="px-3 py-2.5 text-center font-bold {{ $record->isPassing() ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ number_format($record->score, 2) }}%
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="text-xs text-gray-400 dark:text-gray-600">Minimum lulus: 80% · Dihitung: {{ $record->computed_at?->isoFormat('D MMM Y HH:mm') ?? '-' }}</p>
    @else
        <p class="py-6 text-center text-sm text-gray-400">Belum ada data breakdown.</p>
    @endif
</div>
