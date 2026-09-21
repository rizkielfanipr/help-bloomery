@php
    $user = auth()->user();
    $jobs = $this->serviceRequests;
    $unscheduledCount = $jobs->whereNull('scheduled_date')->count();
    $workingCount = $jobs->where('status', \App\Enums\ServiceRequestStatus::InProgress)->count();
@endphp

<div class="min-h-dvh bg-slate-50 pb-28 dark:bg-gray-950">
    <header class="relative overflow-hidden bg-blue-600 px-5 pb-5 pt-6 text-white">
        <div class="pointer-events-none absolute -right-12 -top-16 h-40 w-40 rounded-full border-[32px] border-white/5" aria-hidden="true"></div>
        <div class="relative flex items-center justify-between gap-4">
            <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}" aria-label="Kembali ke Launcher" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 transition hover:bg-white/20">
                <x-heroicon-o-arrow-left class="h-5 w-5" />
            </a>
            <span class="rounded-full border border-white/20 px-3 py-1.5 text-xs font-medium text-blue-100">{{ now()->locale('id')->isoFormat('D MMM YYYY') }}</span>
        </div>
        <div class="relative mt-4 flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white/15">
                <x-heroicon-o-wrench-screwdriver class="h-5 w-5" />
            </div>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-blue-200">Technician Workspace</p>
                <h1 class="mt-1 text-xl font-bold tracking-tight">Logbook Teknisi</h1>
                <p class="mt-1 text-xs leading-5 text-blue-100">Pantau jadwal, tangani kendala, dan catat hasil perbaikan.</p>
            </div>
        </div>
        <div class="relative mt-3 flex items-center gap-2 border-t border-white/15 pt-3 text-xs text-blue-100">
            <x-heroicon-o-user-circle class="h-4 w-4 shrink-0" />
            <span class="truncate">{{ $user->name }}</span>
        </div>
    </header>

    <main class="space-y-6 px-5 py-6">
        <section aria-label="Ringkasan Pekerjaan" class="grid grid-cols-3 gap-2">
            @foreach([
                ['label' => 'Total Aktif', 'value' => $jobs->count(), 'icon' => 'heroicon-o-clipboard-document-list', 'color' => 'text-blue-600 dark:text-blue-400'],
                ['label' => 'Belum Dijadwal', 'value' => $unscheduledCount, 'icon' => 'heroicon-o-calendar-days', 'color' => 'text-amber-600 dark:text-amber-400'],
                ['label' => 'Dikerjakan', 'value' => $workingCount, 'icon' => 'heroicon-o-wrench-screwdriver', 'color' => 'text-emerald-600 dark:text-emerald-400'],
            ] as $summary)
                <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                    @svg($summary['icon'], 'h-5 w-5 '.$summary['color'])
                    <p class="mt-3 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $summary['value'] }}</p>
                    <p class="mt-1 text-[10px] font-medium leading-4 text-slate-500 dark:text-gray-400">{{ $summary['label'] }}</p>
                </div>
            @endforeach
        </section>

        <section class="space-y-3" aria-label="Daftar Pekerjaan">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Pekerjaan Aktif</h2>
                    <p class="mt-1 text-xs text-slate-500">Pilih pekerjaan untuk melihat detail dan tindak lanjut.</p>
                </div>
                <span class="shrink-0 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">{{ $jobs->count() }}</span>
            </div>

            @forelse($jobs as $job)
                @php
                    $statusClass = match($job->status->value) {
                        'in_progress' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300',
                        'scheduled' => 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300',
                        're_submitted' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300',
                        'awaiting_verification' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
                        default => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
                    };
                    $isUrgent = in_array($job->priority, ['urgent', 'high']);
                @endphp
                <a href="{{ \App\Filament\Casual\Resources\ServiceRequests\ServiceRequestResource::getUrl('view', ['record' => $job]) }}" wire:key="technician-job-{{ $job->id }}" class="group block overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:border-blue-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-blue-600">
                    <div class="p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-xs font-semibold text-slate-500 dark:text-gray-400">{{ $job->code }}</span>
                            <span class="rounded-md border px-2 py-1 text-[10px] font-bold {{ $statusClass }}">{{ $job->status->getLabel() }}</span>
                        </div>
                        <div class="mt-4 flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 dark:bg-gray-800 dark:text-gray-400">
                                <x-heroicon-o-cube class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-bold leading-5 text-slate-900 dark:text-white">{{ $job->asset?->name ?? 'Permintaan Perbaikan' }}</h3>
                                <p class="mt-1 text-[11px] text-slate-500 dark:text-gray-400">{{ $job->asset?->asset_number ?? 'Tanpa Asset' }}</p>
                            </div>
                            @if($isUrgent)
                                <span class="inline-flex shrink-0 items-center gap-1 rounded-md bg-red-50 px-2 py-1 text-[10px] font-bold text-red-600 dark:bg-red-950/40 dark:text-red-300"><x-heroicon-o-bolt class="h-3 w-3" />{{ ucfirst($job->priority) }}</span>
                            @endif
                        </div>
                        <p class="mt-3 line-clamp-2 text-xs leading-5 text-slate-600 dark:text-gray-400">{{ $job->requestor_notes ?: 'Belum ada deskripsi kendala.' }}</p>
                        @if($job->status->value === 're_submitted')
                            <p class="mt-3 flex items-center gap-1.5 text-[11px] font-semibold text-red-600 dark:text-red-400"><x-heroicon-o-arrow-path class="h-3.5 w-3.5 shrink-0" />Pengaduan garansi perlu ditindaklanjuti.</p>
                        @endif
                        <div class="mt-4 grid gap-2 border-t border-slate-100 pt-3 text-[11px] text-slate-500 dark:border-gray-800 dark:text-gray-400">
                            <div class="flex items-center gap-2"><x-heroicon-o-building-office-2 class="h-4 w-4 shrink-0" /><span class="truncate">{{ $job->branch?->name ?? 'Cabang Belum Diatur' }}</span></div>
                            <div class="flex items-center gap-2"><x-heroicon-o-calendar-days class="h-4 w-4 shrink-0" /><span>{{ $job->scheduled_date?->format('d M Y') ?? 'Menunggu Penjadwalan Teknisi' }}</span></div>
                            <div class="flex items-center gap-2"><x-heroicon-o-user-circle class="h-4 w-4 shrink-0" /><span class="truncate">{{ $job->technician?->display_username ?? 'Belum Ditugaskan' }}</span></div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/70 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/30">
                        <span class="text-[10px] text-slate-400">Dilaporkan {{ $job->created_at->format('d M Y') }}</span>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 dark:text-blue-400">Lihat Detail <x-heroicon-o-arrow-right class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" /></span>
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center dark:border-gray-700 dark:bg-gray-900">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-500 dark:bg-emerald-950/40"><x-heroicon-o-check-badge class="h-8 w-8" /></div>
                    <h3 class="mt-4 font-bold text-slate-900 dark:text-white">Tidak Ada Pekerjaan Aktif</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Pekerjaan baru akan muncul di sini setelah permintaan masuk.</p>
                    <a href="{{ \App\Filament\Casual\Pages\TechnicianHistoryPage::getUrl(panel: 'casual') }}" class="mt-5 inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-blue-600 dark:border-gray-700 dark:text-blue-400"><x-heroicon-o-clock class="h-4 w-4" />Lihat Riwayat</a>
                </div>
            @endforelse
        </section>
    </main>
    <x-technician.bottom-nav active="jobs" />
</div>
