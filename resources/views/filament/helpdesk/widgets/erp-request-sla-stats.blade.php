<div class="space-y-3">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->getStats() as $stat)
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                <p class="mt-2 text-lg font-semibold text-blue-600 dark:text-blue-400">{{ $stat['value'] }}</p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $stat['description'] }}</p>
            </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400">Senin–Jumat 08.00–17.00 WIB · Mengikuti seluruh hasil filter daftar tiket.</p>
    <div>
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">KPI Ticketing · Masalah ringan</h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Project dan CMS tidak masuk kedua KPI ini. Mengikuti seluruh hasil filter daftar.</p>
    </div>
    <div class="grid gap-3 lg:grid-cols-2">
        @foreach ($this->getKpis() as $kpi)
            <section class="space-y-2 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $kpi['label'] }}</h3>
                <p class="text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ $kpi['value'] }}</p>
                <p class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $kpi['target'] }}</p>
                <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $kpi['description'] }}</p>
                <p @class([
                    'text-xs font-semibold',
                    'text-gray-500 dark:text-gray-400' => $kpi['target_met'] === null,
                    'text-amber-700 dark:text-amber-300' => $kpi['target_met'] !== null && $kpi['provisional'],
                    'text-emerald-700 dark:text-emerald-300' => $kpi['target_met'] === true && ! $kpi['provisional'],
                    'text-red-700 dark:text-red-300' => $kpi['target_met'] === false && ! $kpi['provisional'],
                ])>
                    @if ($kpi['target_met'] === null)
                        Belum ada sampel yang bisa dinilai
                    @else
                        {{ $kpi['provisional'] ? 'Sementara · ' : '' }}{{ $kpi['target_met'] ? 'Target tercapai' : 'Target belum tercapai' }}
                    @endif
                </p>
            </section>
        @endforeach
    </div>
    <x-erp-request.sla-explanation />
</div>
