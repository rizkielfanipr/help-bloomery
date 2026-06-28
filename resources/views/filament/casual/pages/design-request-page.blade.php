@php
    $user     = auth()->user();
    $branch   = $user->branch?->name ?? 'Tanpa Cabang';
    $categories = $this->getCategories();
@endphp

<div class="flex flex-col bg-pink-600 dark:bg-pink-900" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         PINK HEADER
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Request Design</span>
        </div>

        <p class="text-pink-200">{{ $branch }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        <div class="flex flex-col gap-4 px-5">

            {{-- 1. Branch / Divisi (read-only) --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <p class="border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Branch / Divisi
                </p>
                <div class="flex items-center gap-2.5 px-4 py-3">
                    <svg class="h-4 w-4 flex-shrink-0 text-pink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"/>
                    </svg>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $branch }}</p>
                </div>
            </div>

            {{-- 2. Judul Permintaan Design --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label for="judulPermintaan"
                       class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Judul Permintaan Design
                    <span class="ml-1 text-red-400">*</span>
                </label>
                <div class="px-4 py-3">
                    <input id="judulPermintaan"
                           type="text"
                           wire:model="judulPermintaan"
                           placeholder="Contoh: Banner Promo Lebaran 2025..."
                           class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200">
                </div>
                @error('judulPermintaan')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- 3. Kategori Design --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label for="designCategoryId"
                       class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Kategori Design
                    <span class="ml-1 text-red-400">*</span>
                </label>
                <div class="px-4 py-3">
                    <select id="designCategoryId"
                            wire:model="designCategoryId"
                            class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 focus:ring-0 dark:text-slate-200">
                        <option value="">-- Pilih kategori --</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                @error('designCategoryId')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- 4. Brief Design --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label for="ringkasanBrief"
                       class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Brief Design
                    <span class="ml-1 text-red-400">*</span>
                </label>
                <div class="px-4 py-3">
                    <textarea id="ringkasanBrief"
                              wire:model="ringkasanBrief"
                              rows="5"
                              placeholder="Deskripsikan kebutuhan design Anda: tujuan, target audience, warna, referensi, ukuran, deadline, dll..."
                              class="w-full resize-none border-0 bg-transparent p-0 text-sm leading-relaxed text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200"></textarea>
                </div>
                @error('ringkasanBrief')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- 5. Lampiran (optional) --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <p class="border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Lampiran
                    <span class="ml-1 text-[10px] font-normal normal-case text-slate-400">(opsional · maks. 10 MB per file)</span>
                </p>
                <div class="px-4 py-3">

                    {{-- Uploaded files list --}}
                    @if(count($attachments) > 0)
                        <div class="mb-3 space-y-2">
                            @foreach($attachments as $index => $file)
                                <div class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <svg class="h-4 w-4 flex-shrink-0 text-pink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
                                        </svg>
                                        <span class="truncate text-xs text-slate-600 dark:text-slate-300">{{ $file->getClientOriginalName() }}</span>
                                    </div>
                                    <button type="button"
                                            wire:click="removeAttachment({{ $index }})"
                                            class="flex-shrink-0 text-red-400 transition hover:text-red-600">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Upload button --}}
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border-2 border-dashed border-pink-200 bg-pink-50/50 px-4 py-3 transition hover:border-pink-300 hover:bg-pink-50 dark:border-pink-800 dark:bg-pink-950/20">
                        <svg class="h-5 w-5 text-pink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                        </svg>
                        <span class="text-sm font-medium text-pink-600 dark:text-pink-400">Tambah File / Referensi</span>
                        <input type="file"
                               wire:model="attachments"
                               multiple
                               class="hidden">
                    </label>
                    @error('attachments.*')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

        </div>
    </div>

    {{-- ════════════════════════════════════════════
         FIXED BOTTOM SUBMIT BUTTON
    ════════════════════════════════════════════ --}}
    <div class="fixed bottom-0 left-0 right-0 border-t border-gray-200 bg-white px-5 pb-safe-bottom pt-4 pb-8 shadow-lg dark:border-gray-800 dark:bg-gray-900">
        <button type="button"
                wire:click="submit"
                wire:loading.attr="disabled"
                class="w-full rounded-2xl bg-pink-600 py-4 text-sm font-bold text-white shadow-sm transition active:bg-pink-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="submit">Kirim Permintaan Design</span>
            <span wire:loading wire:target="submit">Mengirim...</span>
        </button>
    </div>

    <x-filament-actions::modals />
</div>
