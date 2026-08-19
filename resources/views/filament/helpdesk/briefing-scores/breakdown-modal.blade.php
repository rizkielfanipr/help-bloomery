@php
    $breakdown = $record->breakdown ?? [];
    $periodOrder = ['daily', 'weekly', 'monthly'];
@endphp

<div class="max-h-[70vh] space-y-4 overflow-y-auto overscroll-contain py-2 pr-1">

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

    {{-- Breakdown per periode --}}
    @if(count($breakdown))
        <div class="space-y-3">
            @foreach($periodOrder as $period)
                @continue(! isset($breakdown[$period]))
                @php $info = $breakdown[$period]; @endphp
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                    <div class="flex items-center justify-between bg-gray-50 px-4 py-2.5 dark:bg-gray-800/50">
                        <div>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $info['label'] }}</span>
                            <span class="ml-2 text-xs text-gray-400">Bobot {{ number_format($info['effective_weight'], 2) }}%</span>
                        </div>
                        <div class="text-right">
                            <span class="mr-2 text-xs text-gray-400">Rate {{ number_format($info['rate'], 2) }}%</span>
                            <span class="font-semibold {{ $info['score'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                                {{ number_format($info['score'], 2) }}%
                            </span>
                        </div>
                    </div>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-50 dark:divide-white/[0.04]">
                            @foreach($info['tasks'] as $task)
                                <tr class="hover:bg-gray-50/60 dark:hover:bg-white/[0.02]">
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $task['label'] }}</td>
                                    <td class="px-3 py-2 text-center text-xs text-gray-500 dark:text-gray-400">{{ $task['approved'] }}/{{ $task['expected'] }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold {{ $task['rate'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">{{ number_format($task['rate'], 2) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-400 dark:text-gray-600">Minimum lulus: 80% · Dihitung: {{ $record->computed_at?->isoFormat('D MMM Y HH:mm') ?? '-' }}</p>
    @else
        <p class="py-6 text-center text-sm text-gray-400">Belum ada data breakdown.</p>
    @endif
</div>
