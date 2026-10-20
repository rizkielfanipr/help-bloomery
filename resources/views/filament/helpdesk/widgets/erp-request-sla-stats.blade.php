@php
    $stats = $this->getStats();
    $kpis = $this->getKpis();
    $statPresentation = [
        ['icon' => 'heroicon-o-clock', 'tone' => 'blue'],
        ['icon' => 'heroicon-o-check-circle', 'tone' => 'emerald'],
        ['icon' => 'heroicon-o-chat-bubble-left-ellipsis', 'tone' => 'amber'],
        ['icon' => 'heroicon-o-queue-list', 'tone' => 'rose'],
    ];
@endphp

<div class="w-full min-w-0 space-y-4">
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                    <x-heroicon-o-chart-bar-square class="h-6 w-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">IT Support · Performance</p>
                    <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white sm:text-2xl">Ringkasan SLA Permintaan IT</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Pantau kecepatan respons, penyelesaian tiket, dan capaian KPI Ticketing dari seluruh data yang sesuai filter daftar.</p>
                </div>
            </div>
            <div class="inline-flex w-fit shrink-0 items-center gap-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">
                <x-heroicon-o-calendar-days class="h-4 w-4" />
                Senin–Jumat · 08.00–17.00 WIB
            </div>
        </div>

        <div class="border-t border-gray-200 p-4 dark:border-gray-700 sm:p-5">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($stats as $index => $stat)
                    @php
                        $presentation = $statPresentation[$index];
                        $toneClasses = match ($presentation['tone']) {
                            'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300',
                            'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300',
                            'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300',
                            default => 'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300',
                        };
                    @endphp
                    <article class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <p class="min-w-0 text-xs font-semibold leading-5 text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $toneClasses }}">
                                <x-dynamic-component :component="$presentation['icon']" class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 break-words text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
                        <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $stat['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="flex items-start gap-2 border-t border-gray-200 bg-gray-50/60 px-5 py-3 text-xs leading-5 text-gray-500 dark:border-gray-700 dark:bg-gray-800/30 dark:text-gray-400 sm:px-6">
            <x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" />
            <span>Semua ringkasan mengikuti seluruh hasil filter daftar tiket, bukan hanya data pada halaman tabel yang sedang dibuka.</span>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-start gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700 sm:px-6">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                <x-heroicon-o-presentation-chart-line class="h-5 w-5" />
            </div>
            <div class="min-w-0">
                <h2 class="font-bold text-gray-950 dark:text-white">KPI Ticketing · Masalah Ringan</h2>
                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Project dan CMS tidak masuk kedua KPI. Nilai tetap mengikuti seluruh hasil filter daftar.</p>
            </div>
        </div>

        <div class="grid gap-4 p-4 sm:p-5 lg:grid-cols-2">
            @foreach ($kpis as $kpi)
                @php
                    $statusLabel = match (true) {
                        $kpi['target_met'] === null => 'Belum ada sampel yang bisa dinilai',
                        $kpi['provisional'] && $kpi['target_met'] => 'Sementara · Target tercapai',
                        $kpi['provisional'] => 'Sementara · Target belum tercapai',
                        $kpi['target_met'] => 'Target tercapai',
                        default => 'Target belum tercapai',
                    };
                @endphp
                <article class="flex min-w-0 flex-col rounded-xl border border-gray-200 p-4 dark:border-gray-700 sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-sm font-bold text-gray-950 dark:text-white">{{ $kpi['label'] }}</h3>
                            <p class="mt-3 break-words text-3xl font-bold tracking-tight text-blue-700 dark:text-blue-300">{{ $kpi['value'] }}</p>
                        </div>
                        <span @class([
                            'inline-flex w-fit shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-bold',
                            'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => $kpi['target_met'] === null,
                            'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' => $kpi['target_met'] !== null && $kpi['provisional'],
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300' => $kpi['target_met'] === true && ! $kpi['provisional'],
                            'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300' => $kpi['target_met'] === false && ! $kpi['provisional'],
                        ])>{{ $statusLabel }}</span>
                    </div>

                    <div class="mt-4 flex items-start gap-2 rounded-xl bg-blue-50 p-3 text-xs leading-5 text-blue-800 dark:bg-blue-950/30 dark:text-blue-200">
                        <x-heroicon-o-flag class="mt-0.5 h-4 w-4 shrink-0" />
                        <span class="font-semibold">{{ $kpi['target'] }}</span>
                    </div>
                    <p class="mt-4 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $kpi['description'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <x-erp-request.sla-explanation />
</div>
