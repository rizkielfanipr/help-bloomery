@php
    $maintenance = $this->maintenance();
    $isSubmitted = $maintenance->status === 'submitted';
    $items = $maintenance->items;
    $answered = $items->whereNotNull('result')->count();
    $total = $items->count();
    $sections = $items->groupBy('section_code');
@endphp

<div class="flex min-h-[100dvh] flex-col bg-[#2161f5]">
    <header class="flex-shrink-0 px-5 pb-8 pt-14 text-white">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Pages\TechnicianMaintenancePage::getUrl(panel: 'casual') }}" aria-label="Kembali" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5"/></svg></a>
            <div><p class="text-xl text-blue-100">Detail Audit</p><h1 class="text-xl font-bold uppercase">{{ $maintenance->branch->name }}</h1></div>
        </div>
        <p class="text-xl text-blue-100">{{ $maintenance->maintenance_number }} · {{ $maintenance->checked_at?->format('d M Y') }}</p>
        <div class="mt-5 h-3 overflow-hidden rounded-full bg-white/25"><div class="h-full rounded-full bg-white" style="width: {{ $total ? round($answered / $total * 100) : 0 }}%"></div></div>
    </header>

    <main class="flex-1 overflow-y-auto rounded-t-[2rem] bg-gray-50 px-5 pb-32 pt-6 dark:bg-gray-950">
        <div class="mb-7 overflow-hidden rounded-3xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-4 border-b border-gray-100 p-5 dark:border-gray-800"><div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-900/20"><svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18A2.25 2.25 0 0 0 20.25 17.5V6.108"/></svg></div><div class="min-w-0 flex-1"><p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Nomor Audit</p><p class="truncate text-lg font-bold text-gray-900 dark:text-white">{{ $maintenance->maintenance_number }}</p></div><span class="shrink-0 rounded-full bg-amber-100 px-3 py-2 text-xs font-semibold text-amber-700">{{ $isSubmitted ? 'Terkirim' : 'Sedang Berjalan' }}</span></div>
            <div class="grid grid-cols-2 divide-x divide-gray-100 dark:divide-gray-800"><div class="p-5"><p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Skor</p><p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($maintenance->score, 1) }}%</p><p class="text-sm text-gray-400">Nilai maintenance</p></div><div class="p-5"><p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Progress</p><p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $answered }}/{{ $total }} <span class="text-base font-medium">poin</span></p><p class="text-sm text-gray-400">{{ $total ? round($answered / $total * 100) : 0 }}% selesai</p></div></div>
        </div>

        <form wire:submit="saveDraft" class="space-y-6">
            @foreach($sections as $sectionItems)
                @php $sectionAnswered = $sectionItems->whereNotNull('result')->count(); $sectionTotal = $sectionItems->count(); $sectionName = $sectionItems->first()->section_name; @endphp
                <div><div class="mb-3 flex items-center gap-3"><div class="h-px flex-1 bg-blue-100"></div><span class="flex items-center gap-2 rounded-full bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-600 ring-1 ring-blue-100"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">{{ $loop->iteration }}</span>{{ $sectionName }}</span><div class="h-px flex-1 bg-blue-100"></div></div>
                    <section class="overflow-hidden rounded-3xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10"><div class="border-b border-gray-100 p-5 dark:border-gray-800"><p class="font-semibold text-gray-900 dark:text-white">{{ $sectionName }}</p><p class="text-sm text-gray-400">{{ $sectionAnswered }}/{{ $sectionTotal }} poin terisi</p><div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-blue-500" style="width: {{ $sectionTotal ? round($sectionAnswered / $sectionTotal * 100) : 0 }}%"></div></div></div>
                    @foreach($sectionItems as $item)
                        <details class="group border-b border-gray-100 last:border-0 dark:border-gray-800"><summary class="flex cursor-pointer list-none items-start gap-3 p-5"><span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $item->result === 'pass' ? 'bg-emerald-100 text-emerald-600' : ($item->result === 'fail' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600') }} text-lg">{{ $item->result === 'pass' ? '✓' : ($item->result === 'fail' ? '!' : '−') }}</span><span class="min-w-0 flex-1"><span class="block text-base font-semibold leading-6 text-gray-900 dark:text-white">{{ $item->question }}</span><span class="mt-2 inline-flex rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $item->result === 'pass' ? $item->maximum_points : 0 }}/{{ $item->maximum_points }} poin</span></span><span class="mt-1 text-2xl text-gray-300 transition-transform group-open:rotate-90">›</span></summary><div class="space-y-3 bg-gray-50 px-5 pb-5 pt-1 dark:bg-gray-950/40"><p class="text-xs leading-5 text-gray-500">{{ $item->check_procedure }}</p><select wire:model="results.{{ $item->id }}.result" @disabled($isSubmitted) class="w-full rounded-xl border-0 bg-white px-3 py-3 text-sm ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500"><option value="">Pilih kondisi</option><option value="pass">Baik</option><option value="fail">Perlu perbaikan</option><option value="na">Tidak berlaku</option></select><textarea wire:model="results.{{ $item->id }}.notes" @disabled($isSubmitted) rows="3" class="w-full rounded-xl border-0 bg-white px-3 py-3 text-sm ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500" placeholder="Catatan hasil pengecekan (wajib saat kirim)"></textarea><input type="file" accept="image/*" capture="environment" wire:model="results.{{ $item->id }}.photo" @disabled($isSubmitted) class="block w-full text-xs text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-semibold file:text-blue-600"><p class="text-[11px] text-gray-400">Foto bukti wajib diunggah saat kirim.</p></div></details>
                    @endforeach</section>
                </div>
            @endforeach
            <textarea wire:model="overallNotes" @disabled($isSubmitted) rows="3" class="w-full rounded-2xl border-0 bg-white px-4 py-3 text-sm ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10" placeholder="Catatan keseluruhan (opsional)"></textarea>
            @unless($isSubmitted)<button type="submit" wire:loading.attr="disabled" class="w-full rounded-2xl bg-blue-600 px-4 py-3.5 font-semibold text-white disabled:opacity-60">Simpan Draft</button><button type="button" wire:click="submit" wire:loading.attr="disabled" class="w-full rounded-2xl bg-blue-700 px-4 py-3.5 font-semibold text-white disabled:opacity-60">Kirim Maintenance</button>@endunless
        </form>
    </main>
    <x-technician.bottom-nav active="maintenance" />
</div>
