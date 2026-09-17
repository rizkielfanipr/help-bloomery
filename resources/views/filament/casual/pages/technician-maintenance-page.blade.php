@php
    $maintenances = $this->maintenances;
    $maintenanceCount = $maintenances->count();
@endphp

<div class="min-h-dvh bg-slate-50 pb-28 dark:bg-gray-950">
    <header class="relative overflow-hidden bg-blue-600 px-5 pb-5 pt-6 text-white">
        <div class="pointer-events-none absolute -right-12 -top-16 h-40 w-40 rounded-full border-[32px] border-white/5" aria-hidden="true"></div>
        <div class="relative flex items-center justify-between gap-3">
            <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}" aria-label="Kembali ke Launcher" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 transition hover:bg-white/20"><x-heroicon-o-arrow-left class="h-5 w-5" /></a>
            <span class="rounded-full border border-white/20 px-3 py-1.5 text-xs font-medium text-blue-100">{{ now()->locale('id')->isoFormat('MMM YYYY') }}</span>
        </div>
        <div class="relative mt-4 flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white/15"><x-heroicon-o-clipboard-document-check class="h-5 w-5" /></div>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-blue-200">Technician Workspace</p>
                <h1 class="mt-1 text-xl font-bold tracking-tight">Maintenance Teknisi</h1>
                <p class="mt-1 text-xs leading-5 text-blue-100">Pengecekan rutin cabang dan dokumentasi kondisi operasional.</p>
            </div>
        </div>
    </header>

    <main class="space-y-6 px-5 py-6">
        <section aria-label="Ringkasan Maintenance" class="grid grid-cols-3 gap-2">
            @foreach([
                ['label' => 'Total Laporan', 'value' => $maintenanceCount, 'icon' => 'heroicon-o-clipboard-document-list', 'color' => 'text-blue-600 dark:text-blue-400'],
                ['label' => 'Draft', 'value' => $maintenances->where('status', 'draft')->count(), 'icon' => 'heroicon-o-pencil-square', 'color' => 'text-amber-600 dark:text-amber-400'],
                ['label' => 'Terkirim', 'value' => $maintenances->where('status', 'submitted')->count(), 'icon' => 'heroicon-o-paper-airplane', 'color' => 'text-emerald-600 dark:text-emerald-400'],
            ] as $summary)
                <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                    @svg($summary['icon'], 'h-5 w-5 '.$summary['color'])
                    <p class="mt-3 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $summary['value'] }}</p>
                    <p class="mt-1 text-[10px] font-medium text-slate-500 dark:text-gray-400">{{ $summary['label'] }}</p>
                </div>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-slate-100 p-4 dark:border-gray-800">
                <h2 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white"><x-heroicon-o-clipboard-document-check class="h-4 w-4 text-blue-600" />Mulai Pemeriksaan</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">Pilih cabang dan tanggal pengecekan untuk membuka checklist maintenance bulan berjalan.</p>
            </div>
            <form wire:submit="startCurrentMonth" class="space-y-4 p-4">
                <div><label for="maintenance-branch" class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-gray-300">Cabang Yang Dicek</label><select id="maintenance-branch" wire:model="branchId" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"><option value="">Pilih Cabang</option>@foreach($this->branches() as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>@error('branchId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror</div>
                <div><label for="maintenance-date" class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-gray-300">Tanggal Pengecekan</label><input id="maintenance-date" type="date" wire:model="checkedAt" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">@error('checkedAt')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror</div>
                <button type="submit" wire:loading.attr="disabled" wire:target="startCurrentMonth" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-blue-700 disabled:opacity-60"><x-heroicon-o-plus class="h-4 w-4" /><span wire:loading.remove wire:target="startCurrentMonth">Mulai Pengecekan</span><span wire:loading wire:target="startCurrentMonth">Membuka Checklist...</span></button>
            </form>
        </section>

        <section class="space-y-3" aria-label="Riwayat Maintenance">
            <div class="flex items-center justify-between gap-3"><div><h2 class="text-base font-bold text-slate-900 dark:text-white">Riwayat Maintenance</h2><p class="mt-1 text-xs text-slate-500">Lanjutkan draft atau lihat laporan pengecekan sebelumnya.</p></div><span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">{{ $maintenanceCount }}</span></div>
            @forelse($maintenances as $maintenance)
                <a href="{{ \App\Filament\Casual\Pages\TechnicianMaintenanceDetailPage::getUrl(['record' => $maintenance->id], panel: 'casual') }}" wire:key="maintenance-{{ $maintenance->id }}" class="group block overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:border-blue-400 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-blue-600">
                    <div class="p-4">
                        <div class="flex items-center justify-between gap-2"><span class="font-mono text-[11px] text-slate-500">{{ $maintenance->maintenance_number }}</span><span class="rounded-md border px-2 py-1 text-[10px] font-bold {{ $maintenance->status === 'submitted' ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ $maintenance->status === 'submitted' ? 'Terkirim' : 'Draft' }}</span></div>
                        <div class="mt-4 flex items-center gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 dark:bg-gray-800"><x-heroicon-o-building-office-2 class="h-5 w-5" /></span><div class="min-w-0"><h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $maintenance->branch->name }}</h3><p class="mt-1 text-[11px] text-slate-500">Periode {{ str_pad((string) $maintenance->maintenance_month, 2, '0', STR_PAD_LEFT) }}/{{ $maintenance->maintenance_year }}</p></div></div>
                        <p class="mt-3 flex items-center gap-2 text-[11px] text-slate-500"><x-heroicon-o-calendar-days class="h-4 w-4" />Tanggal Pengecekan: {{ $maintenance->checked_at->format('d M Y') }}</p>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/70 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/30"><span class="text-[10px] text-slate-400">{{ $maintenance->status === 'submitted' ? 'Laporan Pengecekan' : 'Checklist Belum Dikirim' }}</span><span class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 dark:text-blue-400">{{ $maintenance->status === 'submitted' ? 'Lihat Detail' : 'Lanjutkan' }}<x-heroicon-o-arrow-right class="h-3.5 w-3.5" /></span></div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center dark:border-gray-700 dark:bg-gray-900"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-500 dark:bg-blue-950/40"><x-heroicon-o-clipboard-document-check class="h-7 w-7" /></div><h3 class="mt-4 text-sm font-bold text-slate-900 dark:text-white">Belum Ada Maintenance</h3><p class="mt-2 text-xs leading-5 text-slate-500">Pilih cabang dan mulai pengecekan untuk membuat laporan pertama.</p></div>
            @endforelse
        </section>
    </main>
    <x-technician.bottom-nav active="maintenance" />
</div>
