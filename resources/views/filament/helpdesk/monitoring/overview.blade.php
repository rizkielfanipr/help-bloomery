<x-filament-panels::page>
    @php
        $snapshot = $this->snapshot();
        $canSeeTechnical = $this->canSeeTechnical();
        $canAcknowledge = $this->canAcknowledge();
        $notifications = $this->myNotifications();
        $at = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone('Asia/Jakarta') : null;
        $sectionClass = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6';
        $max24 = max(1, collect($snapshot['trend_24h'])->max('problems'));
        $max7 = max(1, collect($snapshot['trend_7d'])->max('problems'));
        $tx = $snapshot['transactions'];
    @endphp

    <div class="space-y-6" wire:poll.60s>
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-4 p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-heart class="h-6 w-6" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">System Monitoring · {{ $snapshot['environment'] }}</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">Kesehatan Sistem</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Snapshot lokal terakhir {{ $at($snapshot['generated_at'])->format('d M Y H:i:s') }} WIB · diperbarui otomatis setiap menit, tanpa memanggil ESB.</p>
                    </div>
                </div>
                <button type="button" wire:click="refreshSnapshot" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                    <x-heroicon-o-arrow-path class="h-4 w-4" wire:loading.class="animate-spin" wire:target="refreshSnapshot" aria-hidden="true" />Muat Ulang Snapshot
                </button>
            </div>
            @if ($snapshot['maintenance'])
                <div class="flex items-start gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-200 sm:px-6">
                    <x-heroicon-o-wrench-screwdriver class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                    <span><strong>Maintenance {{ $snapshot['in_maintenance'] ? 'sedang berlangsung' : 'terjadwal' }}:</strong> {{ $snapshot['maintenance']['starts_at']->timezone('Asia/Jakarta')->format('d M H:i') }}–{{ $snapshot['maintenance']['ends_at']->timezone('Asia/Jakarta')->format('d M H:i') }} WIB. {{ $snapshot['maintenance']['reason'] }} Alert ditahan, pemeriksaan tetap dicatat.</span>
                </div>
            @endif
        </section>

        <section aria-labelledby="monitoring-services-heading">
            <h3 id="monitoring-services-heading" class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Status Layanan</h3>
            @if ($snapshot['services'] === [])
                <div class="rounded-2xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700">Belum ada hasil pemeriksaan. Pastikan scheduler menjalankan <code>monitoring:run-checks</code> setiap menit.</div>
            @else
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($snapshot['services'] as $service)
                        <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900" wire:key="service-{{ $service['key'] }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $service['name'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ str($service['group'])->upper() }}{{ $service['critical'] ? ' · Kritis' : '' }}</p>
                                </div>
                                <x-helpdesk.monitoring.status-badge :label="$service['status_label']" :color="$service['color']" :icon="$service['icon']" />
                            </div>
                            <p class="mt-3 line-clamp-2 text-xs leading-5 text-gray-600 dark:text-gray-300">{{ $service['message'] ?? 'Belum diperiksa.' }}</p>
                            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                                {{ $service['last_checked_at'] ? 'Dicek '.$at($service['last_checked_at'])->diffForHumans() : 'Belum pernah dicek' }}{{ $service['response_time_ms'] !== null ? ' · '.$service['response_time_ms'].' ms' : '' }}{{ isset($snapshot['uptime_7d'][$service['key']]) ? ' · uptime 7 hari '.$snapshot['uptime_7d'][$service['key']].'%' : '' }}
                            </p>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
            @foreach (['scheduler' => 'Scheduler Heartbeat', 'queue' => 'Queue Heartbeat'] as $key => $label)
                @php($beat = $snapshot['heartbeats'][$key])
                <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-2"><p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p><x-helpdesk.monitoring.status-badge :label="$beat['label']" :color="$beat['color']" /></div>
                    <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $beat['age_seconds'] !== null ? \Carbon\CarbonInterval::seconds($beat['age_seconds'])->cascade()->forHumans(short: true).' lalu' : '—' }}</p>
                </section>
            @endforeach
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">Transaksi ESB perlu tindakan (30 hari)</p>
                <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $tx['unknown'] }} unknown · {{ $tx['failed'] }} failed · {{ $tx['partial'] }} partial</p>
                @if ($canSeeTechnical)
                    <a href="{{ \App\Filament\Helpdesk\Resources\MonitoringTransactions\MonitoringTransactionResource::getUrl('index') }}" class="mt-1 inline-block text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">Buka ESB Transactions</a>
                @endif
            </section>
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">Error aktif</p>
                <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $snapshot['errors']['last_1h'] }} (1 jam) · {{ $snapshot['errors']['last_24h'] }} (24 jam)</p>
                <p class="text-xs text-gray-500">{{ $snapshot['errors']['untriaged'] }} belum ditriage</p>
            </section>
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">Insiden terbuka</p>
                <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $snapshot['open_incident_count'] }}</p>
            </section>
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">Deployment terakhir</p>
                @if ($snapshot['deployment'])
                    <p class="mt-2 flex flex-wrap items-center gap-2 text-sm font-bold text-gray-900 dark:text-white"><span class="font-mono">{{ $snapshot['deployment']['commit'] ?? '—' }}</span><x-helpdesk.monitoring.status-badge :label="$snapshot['deployment']['status']" :color="$snapshot['deployment']['color']" /></p>
                    <p class="text-xs text-gray-500">{{ $snapshot['deployment']['environment'] }} · {{ $at($snapshot['deployment']['started_at'])?->format('d M H:i') }} WIB{{ $snapshot['deployment']['long_running'] ? ' · melebihi batas durasi' : '' }}</p>
                @else
                    <p class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Not Configured</p>
                    <p class="text-xs text-gray-500">Belum ada metadata deployment yang dikirim.</p>
                @endif
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            @foreach ([['title' => 'Pemeriksaan bermasalah per jam (24 jam)', 'rows' => $snapshot['trend_24h'], 'max' => $max24, 'key' => 'problems'], ['title' => 'Pemeriksaan bermasalah per hari (7 hari, dari aggregate)', 'rows' => $snapshot['trend_7d'], 'max' => $max7, 'key' => 'problems']] as $chart)
                <section class="{{ $sectionClass }}">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $chart['title'] }}</h3>
                    @if (collect($chart['rows'])->sum('total') === 0)
                        <p class="mt-4 text-sm text-gray-500">Belum ada data pemeriksaan pada periode ini.</p>
                    @else
                        <div class="mt-4 flex h-28 items-end gap-0.5 border-b border-gray-200 dark:border-gray-700" aria-hidden="true">
                            @foreach ($chart['rows'] as $row)
                                <div class="group relative flex h-full flex-1 items-end" title="{{ $row['label'] }}: {{ $row[$chart['key']] }} bermasalah dari {{ $row['total'] }} pemeriksaan">
                                    <div class="w-full rounded-t bg-blue-500 transition group-hover:bg-blue-700 dark:bg-blue-400" style="height: {{ $row[$chart['key']] > 0 ? max(4, round($row[$chart['key']] / $chart['max'] * 100)) : 0 }}%"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-1 flex justify-between text-[11px] text-gray-500"><span>{{ $chart['rows'][0]['label'] }}</span><span>{{ end($chart['rows'])['label'] }}</span></div>
                        <table class="sr-only">
                            <caption>{{ $chart['title'] }}</caption>
                            <thead><tr><th scope="col">Periode</th><th scope="col">Bermasalah</th><th scope="col">Total</th></tr></thead>
                            <tbody>@foreach ($chart['rows'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row[$chart['key']] }}</td><td>{{ $row['total'] }}</td></tr>@endforeach</tbody>
                        </table>
                    @endif
                </section>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="{{ $sectionClass }}">
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Alert Aktif</h3>
                <ul class="space-y-3">
                    @forelse ($snapshot['alerts'] as $alert)
                        <li class="flex flex-col gap-2 rounded-xl border border-gray-200 p-3 sm:flex-row sm:items-start sm:justify-between dark:border-gray-700" wire:key="alert-{{ $alert['id'] }}">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><x-helpdesk.monitoring.status-badge :label="$alert['severity']" :color="$alert['severity_color']" />{{ $alert['title'] }}</p>
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $alert['message'] }}</p>
                                <p class="mt-1 text-[11px] text-gray-500">Sejak {{ $at($alert['opened_at'])->format('d M H:i') }} WIB{{ $alert['acknowledged'] ? ' · sudah diakui' : '' }}</p>
                            </div>
                            @if ($canAcknowledge && ! $alert['acknowledged'])
                                <button type="button" wire:click="acknowledgeAlert({{ $alert['id'] }})" wire:loading.attr="disabled" class="shrink-0 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">Akui</button>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">Tidak ada alert aktif.</li>
                    @endforelse
                </ul>
            </section>

            <section class="{{ $sectionClass }}">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Insiden Terbuka</h3>
                    <a href="{{ \App\Filament\Helpdesk\Resources\MonitoringIncidents\MonitoringIncidentResource::getUrl('index') }}" class="text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">Incident Center</a>
                </div>
                <ul class="space-y-3">
                    @forelse ($snapshot['incidents'] as $incident)
                        <li class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <a href="{{ \App\Filament\Helpdesk\Resources\MonitoringIncidents\MonitoringIncidentResource::getUrl('view', ['record' => $incident['uuid']]) }}" class="flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-900 hover:underline dark:text-white"><x-helpdesk.monitoring.status-badge :label="$incident['severity']" :color="$incident['severity_color']" />{{ $incident['number'] }} — {{ $incident['title'] }}</a>
                            <p class="mt-1 text-[11px] text-gray-500">{{ $incident['status'] }} · terdeteksi {{ $at($incident['detected_at'])->format('d M H:i') }} WIB</p>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">Tidak ada insiden terbuka.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        @if ($notifications->isNotEmpty())
            <section class="{{ $sectionClass }}">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Notifikasi Monitoring Saya</h3>
                    <button type="button" wire:click="markNotificationsRead" class="text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">Tandai sudah dibaca</button>
                </div>
                <ul class="space-y-2">
                    @foreach ($notifications as $notification)
                        <li class="text-sm text-gray-700 dark:text-gray-300"><span class="font-semibold">{{ $notification['title'] }}</span> · <span class="text-xs text-gray-500">{{ $at($notification['created_at'])->diffForHumans() }}</span></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-filament-panels::page>
