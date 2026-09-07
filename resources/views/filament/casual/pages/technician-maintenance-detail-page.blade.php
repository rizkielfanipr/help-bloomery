@php
    $maintenance = $this->maintenance();
    $isSubmitted = $maintenance->status === 'submitted';
    $items = $maintenance->items;
    $answered = $items->whereNotNull('result')->count();
    $total = $items->count();
@endphp

<div x-data="{ activeItemId: null }" class="technician-maintenance-detail-page flex min-h-[100dvh] flex-col bg-[#2161f5]">
    <header class="flex-shrink-0 px-5 pb-6 pt-14 text-white">
        <div class="mb-3 flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Pages\TechnicianMaintenancePage::getUrl(panel: 'casual') }}" aria-label="Kembali" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5"/></svg></a>
            <div class="min-w-0 flex-1"><p class="text-xs font-medium text-blue-200">Detail Maintenance</p><h1 class="truncate text-base font-semibold">{{ $maintenance->branch->name }}</h1></div>
        </div>
        <p class="mb-2 text-xs text-blue-200">{{ $maintenance->maintenance_number }} · {{ $maintenance->checked_at?->format('d M Y') }}</p>
        <div class="h-1.5 overflow-hidden rounded-full bg-white/20"><div class="h-1.5 rounded-full bg-white" style="width: {{ $total ? round($answered / $total * 100) : 0 }}%"></div></div>
    </header>

    <main class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 px-0 pb-32 pt-5 dark:bg-gray-950">
        <div class="mx-5 mb-5 overflow-hidden rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3.5 dark:border-gray-800"><div class="flex min-w-0 items-center gap-3"><div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/20"><svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18A2.25 2.25 0 0 0 20.25 17.5V6.108"/></svg></div><div class="min-w-0 flex-1"><p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Nomor Maintenance</p><p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $maintenance->maintenance_number }}</p></div></div><span class="flex shrink-0 items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>{{ $isSubmitted ? 'Terkirim' : 'Sedang Berjalan' }}</span></div>
        <form wire:submit="saveDraft">
            <section class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-gray-800 dark:border-gray-800">
                @foreach($items as $item)
                    @php
                        $isAnswered = $item->result !== null;
                        $itemStatus = match (true) {
                            ! $isAnswered => ['icon' => 'dot', 'background' => 'bg-gray-100 dark:bg-gray-800', 'text' => 'text-gray-300 dark:text-gray-600'],
                            $item->result === 'pass' => ['icon' => 'check', 'background' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-600 dark:text-emerald-400'],
                            $item->result === 'fail' => ['icon' => 'x', 'background' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-600 dark:text-red-400'],
                            default => ['icon' => 'dash', 'background' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-600 dark:text-amber-400'],
                        };
                    @endphp
                    <button type="button" @click="activeItemId = {{ $item->id }}" class="flex w-full items-start gap-3 px-4 py-3.5 text-left active:bg-gray-50 dark:active:bg-gray-800/50">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $itemStatus['background'] }} {{ $itemStatus['text'] }}">
                            @if($itemStatus['icon'] === 'check')
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            @elseif($itemStatus['icon'] === 'x')
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            @elseif($itemStatus['icon'] === 'dash')
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                            @else
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                            @endif
                        </span>
                        <span class="min-w-0 flex-1"><span class="block text-sm font-medium leading-5 text-gray-900 dark:text-white">{{ $item->question }}</span></span>
                        <svg class="mt-1 h-4 w-4 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </button>

                    <div x-show="activeItemId === {{ $item->id }}" x-cloak class="fixed inset-0 z-50 flex items-end" style="display: none">
                        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="activeItemId = null"></div>
                        <div class="relative max-h-[85vh] w-full overflow-y-auto rounded-t-3xl bg-white dark:bg-gray-900" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
                            <div class="flex justify-center pb-1 pt-3"><div class="h-1 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div></div>
                            <div class="px-5 pb-2 pt-2"><p class="text-base font-semibold text-gray-900 dark:text-white">{{ $item->question }}</p><p class="mt-0.5 text-sm text-gray-500">{{ $item->check_procedure ?: 'Lengkapi hasil pengecekan rutin.' }}</p></div>
                            <div class="space-y-4 px-5 pb-4 pt-2" wire:key="maintenance-item-{{ $item->id }}">
                                @if($item->photoUrls())
                                    <div><p class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">Foto Bukti Tersimpan</p><div class="grid grid-cols-3 gap-2">@foreach($item->photoUrls() as $index => $url)<a href="{{ $url }}" target="_blank" class="aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"><img src="{{ $url }}" alt="Bukti {{ $index + 1 }}" class="h-full w-full object-cover"></a>@endforeach</div></div>
                                @endif
                                <div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Foto Bukti <span class="ml-1 text-xs font-normal text-red-500">* Wajib</span></label><label class="mt-1.5 flex aspect-square w-28 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-blue-300 bg-blue-50 text-blue-600 transition active:bg-blue-100 dark:border-blue-700 dark:bg-blue-950/30"><svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/></svg><span class="text-xs font-medium">Kamera</span><input type="file" accept="image/*" capture="environment" wire:model="results.{{ $item->id }}.photo" @disabled($isSubmitted) class="sr-only"></label><p class="mt-1.5 text-center text-xs text-gray-400">Foto bukti wajib diunggah saat kirim.</p></div>
                                <div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Catatan <span class="ml-1 text-xs font-normal text-red-500">* Wajib</span></label><textarea wire:model="results.{{ $item->id }}.notes" @disabled($isSubmitted) rows="3" class="mt-1.5 w-full resize-none rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white" placeholder="Tambahkan catatan..."></textarea></div>
                                <p class="text-[11px] text-gray-400">Dibuat {{ $item->created_at?->format('d M Y, H:i') ?? '—' }} · Terakhir diubah {{ $item->updated_at?->format('d M Y, H:i') ?? '—' }}</p>
                            </div>
                            <div class="flex gap-3 px-5 pb-10"><button type="button" @click="activeItemId = null" class="flex-1 rounded-2xl border border-gray-200 py-3.5 text-sm font-semibold text-gray-600 transition active:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Batal</button><button type="button" @click="activeItemId = null" wire:click="saveDraft" wire:loading.attr="disabled" class="flex-1 rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-sm transition active:bg-blue-700 disabled:opacity-60">Simpan</button></div>
                        </div>
                    </div>
                @endforeach
            </section>
            @unless($isSubmitted)<div class="border-t border-gray-100 p-4 dark:border-gray-800"><button type="button" wire:click="submit" wire:loading.attr="disabled" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition active:bg-blue-700 disabled:opacity-60"><x-heroicon-o-paper-airplane class="h-4 w-4" />Kirim Maintenance</button></div>@endunless
        </form>
        </div>
    </main>
    <x-technician.bottom-nav active="maintenance" />
</div>
