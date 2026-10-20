<x-filament-panels::page>
    @php
        $services = $this->services;
        $last24h = $this->last24h;
        $uptime7d = $this->uptime7d;
        $recent = $this->recentResults;
        $jakarta = fn ($date) => $date ? $date->copy()->timezone('Asia/Jakarta')->format('d M H:i:s') : '-';
    @endphp

    <div class="space-y-6" wire:poll.60s>
        <div class="flex items-start gap-2 rounded-2xl border border-blue-100 bg-blue-50 px-5 py-3 text-sm leading-6 text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300">
            <x-heroicon-o-information-circle class="mt-1 h-4 w-4 shrink-0" aria-hidden="true" />
            <span><strong>Informasi Proses Otomatis:</strong> scheduler menjalankan pemeriksaan read-only sesuai interval. Status berubah ke Down setelah ambang kegagalan berturut-turut dan pulih setelah ambang pemulihan. Halaman ini tidak memanggil layanan eksternal.</span>
        </div>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            @if ($services->isEmpty())
                <p class="p-6 text-sm text-gray-500">Belum ada layanan terdaftar. Layanan dibuat otomatis saat <code>monitoring:run-checks</code> pertama kali berjalan.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Layanan</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3">Pesan</th>
                                <th scope="col" class="px-4 py-3 text-right">Respons</th>
                                <th scope="col" class="px-4 py-3">Dicek</th>
                                <th scope="col" class="px-4 py-3 text-right">Bermasalah 24 jam</th>
                                <th scope="col" class="px-4 py-3 text-right">Uptime 7 hari</th>
                                <th scope="col" class="px-4 py-3">Ambang</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Riwayat</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($services as $service)
                                @php($stats = $last24h[$service->id] ?? ['total' => 0, 'problems' => 0])
                                <tr wire:key="service-row-{{ $service->key }}" @class(['bg-blue-50/50 dark:bg-blue-950/20' => $selectedServiceKey === $service->key])>
                                    <td class="px-4 py-3"><p class="font-semibold text-gray-900 dark:text-white">{{ $service->name }}</p><p class="text-xs text-gray-500">{{ $service->key }}{{ $service->is_critical ? ' · kritis' : '' }}{{ $service->is_enabled ? '' : ' · nonaktif' }}</p></td>
                                    <td class="px-4 py-3">
                                        @php($status = $service->isStale() && $service->is_enabled ? \App\Enums\MonitoringServiceStatus::Unknown : $service->last_status)
                                        <x-helpdesk.monitoring.status-badge :label="$status->getLabel().($service->isStale() && $service->is_enabled ? ' (data lama)' : '')" :color="$status->getColor()" :icon="$status->icon()" />
                                    </td>
                                    <td class="max-w-xs px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $service->last_safe_message ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right text-xs">{{ $service->last_response_time_ms !== null ? $service->last_response_time_ms.' ms' : '-' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs">{{ $jakarta($service->last_checked_at) }}</td>
                                    <td class="px-4 py-3 text-right text-xs">{{ $stats['problems'] }} / {{ $stats['total'] }}</td>
                                    <td class="px-4 py-3 text-right text-xs">{{ isset($uptime7d[$service->key]) ? $uptime7d[$service->key].'%' : '-' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">{{ $service->failure_threshold }}× gagal · {{ $service->recovery_threshold }}× pulih · {{ $service->check_interval_seconds }}s</td>
                                    <td class="px-4 py-3 text-right"><button type="button" wire:click="selectService('{{ $service->key }}')" class="text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400" aria-expanded="{{ $selectedServiceKey === $service->key ? 'true' : 'false' }}">Riwayat</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($selectedServiceKey)
            <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6">
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">20 hasil pemeriksaan terakhir · {{ $selectedServiceKey }}</h3>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($recent as $result)
                        <li class="flex flex-col gap-1 py-2 text-xs sm:flex-row sm:items-center sm:gap-4">
                            <span class="w-36 shrink-0 text-gray-500">{{ $jakarta($result->checked_at) }}</span>
                            <x-helpdesk.monitoring.status-badge :label="$result->status->getLabel()" :color="$result->status->getColor()" />
                            <span class="text-gray-700 dark:text-gray-300">{{ $result->safe_message }}</span>
                            <span class="text-gray-500 sm:ml-auto">{{ $result->response_time_ms }} ms{{ $result->http_status ? ' · HTTP '.$result->http_status : '' }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-500">Belum ada hasil.</li>
                    @endforelse
                </ul>
            </section>
        @endif
    </div>
</x-filament-panels::page>
