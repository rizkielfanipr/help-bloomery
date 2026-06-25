<div class="flex flex-col bg-violet-600 dark:bg-violet-900"
     style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         VIOLET HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.launcher-page') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Pengajuan Pembelian</span>
        </div>

        <p class="text-violet-200">{{ auth()->user()->branch?->name ?? 'Tanpa Cabang' }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        <div class="flex flex-col gap-4 px-5">

            {{-- Division --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Divisi
                </label>
                <div class="px-4 py-3">
                    <input type="text" wire:model="division"
                           placeholder="Masukkan nama divisi..."
                           class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200">
                </div>
                @error('division')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Item Name --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Nama Barang
                </label>
                <div class="px-4 py-3">
                    <input type="text" wire:model="itemName"
                           placeholder="Nama barang yang diajukan..."
                           class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200">
                </div>
                @error('itemName')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Quantity --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Jumlah Barang
                </label>
                <div class="px-4 py-3">
                    <input type="number" wire:model="quantity" min="1"
                           placeholder="0"
                           class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200">
                </div>
                @error('quantity')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Purchase Reason --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Alasan Pembelian
                </label>
                <div class="px-4 py-3">
                    <textarea wire:model="purchaseReason" rows="3"
                              placeholder="Jelaskan alasan pembelian..."
                              class="w-full resize-none border-0 bg-transparent p-0 text-sm text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200"></textarea>
                </div>
                @error('purchaseReason')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Purchase Type --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Jenis Pembelian
                </label>
                <div class="flex divide-x divide-gray-100 dark:divide-gray-800">
                    <label class="flex flex-1 cursor-pointer items-center gap-3 px-4 py-3.5">
                        <input type="radio" wire:model.live="purchaseType" value="new"
                               class="h-4 w-4 border-gray-300 text-violet-600 focus:ring-violet-500">
                        <span class="text-sm text-slate-700 dark:text-slate-200">Baru</span>
                    </label>
                    <label class="flex flex-1 cursor-pointer items-center gap-3 px-4 py-3.5">
                        <input type="radio" wire:model.live="purchaseType" value="broken"
                               class="h-4 w-4 border-gray-300 text-violet-600 focus:ring-violet-500">
                        <span class="text-sm text-slate-700 dark:text-slate-200">Rusak / Penggantian</span>
                    </label>
                </div>
                @error('purchaseType')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Journal Item Number (conditional) --}}
            @if($purchaseType === 'broken')
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                    <label class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                        Nomor Item Journal
                    </label>
                    <div class="px-4 py-3">
                        <input type="text" wire:model="journalItemNumber"
                               placeholder="Nomor item di jurnal..."
                               class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200">
                    </div>
                    @error('journalItemNumber')
                        <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            {{-- Purchase Request Number --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Nomor Purchase Request
                    <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span>
                </label>
                <div class="px-4 py-3">
                    <input type="text" wire:model="purchaseRequestNumber"
                           placeholder="Nomor PR jika ada..."
                           class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200">
                </div>
                @error('purchaseRequestNumber')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- E-Commerce Link --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Link e-Commerce
                    <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span>
                </label>
                <div class="px-4 py-3">
                    <input type="url" wire:model="ecommerceLink"
                           placeholder="https://..."
                           class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200">
                </div>
                @error('ecommerceLink')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Attachments --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Foto / Lampiran
                    <span class="ml-1 font-normal normal-case text-slate-400">(opsional, maks. 5 MB per file)</span>
                </label>

                @if(count($attachments) > 0)
                    <div class="grid grid-cols-3 gap-2 p-3">
                        @foreach($attachments as $index => $attachment)
                            <div class="relative aspect-square">
                                <img src="{{ $attachment->temporaryUrl() }}"
                                     class="h-full w-full rounded-lg object-cover ring-1 ring-gray-200">
                                <button wire:click="removeAttachment({{ $index }})" type="button"
                                        class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="px-4 py-3">
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-200 py-4 transition hover:border-violet-300 hover:bg-violet-50 dark:border-gray-700 dark:hover:border-violet-600 dark:hover:bg-violet-900/20">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                        </svg>
                        <span class="text-sm text-gray-400">Tambah foto</span>
                        <input type="file" wire:model="attachments" multiple accept="image/*" class="hidden">
                    </label>
                </div>

                @error('attachments.*')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Submit button --}}
            <button wire:click="submit" wire:loading.attr="disabled"
                    class="w-full rounded-2xl bg-violet-600 py-3.5 text-sm font-semibold text-white shadow-sm transition active:scale-95 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">Kirim Pengajuan</span>
                <span wire:loading wire:target="submit">Mengirim...</span>
            </button>

        </div>
    </div>

    {{-- ════════════════════════════════════════════
         BOTTOM NAV
    ════════════════════════════════════════════ --}}
    <x-purchase-request.bottom-nav active="form" />

</div>
