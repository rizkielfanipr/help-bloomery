<x-filament-panels::page>
    @php
        $heartbeats = $this->heartbeats;
        $queueMeta = $this->queueMetadata;
        $tasks = $this->scheduledTasks;
        $failedJobs = $this->failedJobs;
        $jakarta = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->timezone('Asia/Jakarta')->format('d M Y H:i:s') : '-';
        $stateBadge = fn (string $state) => match ($state) {
            'healthy', 'succeeded' => ['Tepat waktu', 'success'],
            'late' => ['Terlambat', 'danger'],
            'failed' => ['Gagal', 'danger'],
            'stuck' => ['Tidak selesai', 'danger'],
            'running' => ['Berjalan', 'info'],
            'skipped' => ['Dilewati', 'warning'],
            default => ['Belum ada riwayat', 'gray'],
        };
        $sectionClass = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6';
    @endphp

    <div class="space-y-6" wire:poll.60s>
        <div class="flex items-start gap-2 rounded-2xl border border-blue-100 bg-blue-50 px-5 py-3 text-sm leading-6 text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300">
            <x-heroicon-o-information-circle class="mt-1 h-4 w-4 shrink-0" aria-hidden="true" />
            <span>Halaman ini read-only. Restart worker, terminal, dan retry failed job tidak tersedia dari UI karena belum ada job yang terbukti aman di-retry.</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach (['scheduler' => 'Scheduler', 'queue' => 'Queue Worker'] as $key => $label)
                @php([$text, $color] = $stateBadge($heartbeats[$key]['state']))
                <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-2"><p class="text-xs text-gray-500">{{ $label }} heartbeat</p><x-helpdesk.monitoring.status-badge :label="$text" :color="$color" /></div>
                    <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $jakarta($heartbeats[$key]['last_beat_at']) }}</p>
                    <p class="text-xs text-gray-500">Terlambat bila &gt; {{ $heartbeats[$key]['late_after'] }} detik{{ $heartbeats[$key]['age'] !== null ? ' · '.$heartbeats[$key]['age'].' detik lalu' : '' }}</p>
                </section>
            @endforeach
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Job pending ({{ $queueMeta['connection'] ?? config('queue.default') }})</p>
                <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $queueMeta['pending_jobs'] ?? 'Tidak tersedia' }}</p>
                <p class="text-xs text-gray-500">Tertua: {{ isset($queueMeta['oldest_pending_age_seconds']) ? $queueMeta['oldest_pending_age_seconds'].' detik' : '-' }}</p>
            </section>
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Failed jobs</p>
                <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $failedJobs?->total() ?? 'Tidak tersedia' }}</p>
            </section>
        </div>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <h3 class="px-5 pt-5 text-sm font-semibold text-gray-900 dark:text-white sm:px-6">Scheduled Tasks</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                        <tr><th scope="col" class="px-4 py-3">Task</th><th scope="col" class="px-4 py-3">Jadwal</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3">Terakhir Mulai</th><th scope="col" class="px-4 py-3 text-right">Durasi</th><th scope="col" class="px-4 py-3">Berikutnya</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($tasks as $task)
                            @php([$text, $color] = $stateBadge($task['state']))
                            <tr wire:key="task-{{ md5($task['key']) }}">
                                <td class="px-4 py-3"><p class="font-mono text-xs text-gray-900 dark:text-white">{{ $task['key'] }}</p>@if ($task['overlap'])<p class="text-[11px] text-gray-500">Overlap dicegah</p>@endif</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $task['expression'] }}</td>
                                <td class="px-4 py-3"><x-helpdesk.monitoring.status-badge :label="$text" :color="$color" />@if ($task['last_run']?->safe_message)<p class="mt-1 max-w-xs text-[11px] text-gray-500">{{ $task['last_run']->safe_message }}</p>@endif</td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs">{{ $jakarta($task['last_run']?->started_at) }}</td>
                                <td class="px-4 py-3 text-right text-xs">{{ $task['last_run']?->duration_ms !== null ? number_format($task['last_run']->duration_ms / 1000, 1).' s' : '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs">{{ $jakarta($task['next_due']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $sectionClass }}">
            <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Failed Jobs (read-only)</h3>
            @if ($failedJobs === null)
                <p class="text-sm text-gray-500">Tabel failed jobs tidak tersedia.</p>
            @elseif ($failedJobs->isEmpty())
                <p class="text-sm text-gray-500">Tidak ada failed job.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($failedJobs as $job)
                        <li class="py-2 text-xs" wire:key="failed-{{ $job['uuid'] }}">
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $job['job'] }} <span class="font-normal text-gray-500">· {{ $job['connection'] }}/{{ $job['queue'] }} · {{ $jakarta($job['failed_at']) }}</span></p>
                            <p class="mt-0.5 break-words text-gray-600 dark:text-gray-300">{{ $job['exception'] }}</p>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3">{{ $failedJobs->links() }}</div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
