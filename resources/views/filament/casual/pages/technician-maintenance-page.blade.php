@php
    $maintenanceCount = $this->maintenances->count();
@endphp

<div class="flex flex-col bg-blue-600" style="min-height:100dvh">
    <x-technician.mobile-page-header title="Maintenance Teknisi">
        <x-slot:icon>
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v12a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 18V6a2.25 2.25 0 0 1 2.25-2.25ZM8.25 8.25h7.5m-7.5 3.75h7.5m-7.5 3.75h3"/></svg>
        </x-slot:icon>
    </x-technician.mobile-page-header>

    <main class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-4 dark:bg-gray-950">
        <div class="flex flex-col gap-4 px-5">
        <div class="flex items-center justify-between">
            <div><p class="text-sm font-semibold text-gray-900 dark:text-white">Maintenance Bulanan</p><p class="mt-0.5 text-xs text-gray-500">Pengecekan rutin store</p></div>
            <span class="flex items-center gap-1.5 rounded-lg bg-gray-200/70 px-3 py-1.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $maintenanceCount }} laporan</span>
        </div>
        <div class="rounded-2xl bg-white p-3.5 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="mb-2.5 text-sm font-semibold text-gray-800 dark:text-gray-100">Mulai pemeriksaan</p>
            <div class="flex flex-col gap-2.5">
                <label class="block"><span class="mb-1 block text-xs font-medium text-gray-500">Branch yang dicek</span><select wire:model="branchId" class="w-full rounded-xl border-0 bg-gray-50 px-3 py-3 text-sm text-gray-700 ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700"><option value="">Pilih branch</option>@foreach($this->branches() as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
                <label class="block"><span class="mb-1 block text-xs font-medium text-gray-500">Tanggal pengecekan</span><input type="date" wire:model="checkedAt" class="w-full rounded-xl border-0 bg-gray-50 px-3 py-3 text-sm text-gray-700 ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700"></label>
            </div>
            @error('branchId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            @error('checkedAt') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            <div class="mt-2 border-t border-gray-100 pt-2.5 dark:border-gray-800">
                <button wire:click="startCurrentMonth" wire:loading.attr="disabled" class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white shadow-sm transition active:scale-[0.98] disabled:opacity-60">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Mulai Pengecekan
                </button>
            </div>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-2.5 px-4 pb-3 pt-4"><div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20"><svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18A2.25 2.25 0 0 0 20.25 17.5V6.108a2.25 2.25 0 0 0-1.976-2.192 48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586"/></svg></div><div><p class="text-sm font-semibold text-gray-900 dark:text-white">Riwayat Maintenance</p><p class="text-xs text-gray-400">{{ $maintenanceCount }} laporan tersimpan</p></div></div>
        @forelse($this->maintenances as $maintenance)
            <a href="{{ \App\Filament\Casual\Pages\TechnicianMaintenanceDetailPage::getUrl(['record' => $maintenance->id], panel: 'casual') }}" class="flex items-center gap-3 border-t border-gray-100 px-3.5 py-3 transition active:bg-gray-50 dark:border-gray-800 dark:active:bg-gray-800">
                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M5.25 21V5.25A2.25 2.25 0 0 1 7.5 3h9a2.25 2.25 0 0 1 2.25 2.25V21M8.25 7.5h7.5M8.25 11.25h7.5M8.25 15h4.5"/></svg></span>
                <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-gray-900 dark:text-white">{{ $maintenance->branch->name }}</span><span class="mt-1 block text-xs text-gray-400">{{ $maintenance->maintenance_month }}/{{ $maintenance->maintenance_year }}</span></span>
                <span class="flex flex-col items-end gap-2"><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $maintenance->status === 'submitted' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }}">{{ $maintenance->status === 'submitted' ? 'Terkirim' : 'Draft' }}</span><svg class="h-4 w-4 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg></span>
            </a>
        @empty
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center"><div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-900/20"><svg class="h-7 w-7 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg></div><p class="mt-4 text-sm text-gray-400">Belum ada maintenance. Tekan tombol di atas untuk mulai.</p></div>
        @endforelse
        </div>
        </div>
    </main>
    <x-technician.bottom-nav active="maintenance" />
</div>
