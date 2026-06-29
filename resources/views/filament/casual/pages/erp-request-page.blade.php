@php
    $user     = auth()->user();
    $branch   = $user->branch?->name ?? 'Tanpa Cabang';
    $modules  = $this->getModules();
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         INDIGO HEADER
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 2.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Request ERP</span>
        </div>

        <p class="text-blue-200">{{ $branch }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        @php $fieldClass = 'w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-slate-700 placeholder-slate-300 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200'; @endphp
        @php $labelClass = 'mb-1.5 block text-xs font-semibold text-slate-600'; @endphp

        <div class="px-5">
            <div class="flex flex-col gap-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">

            {{-- Branch / Divisi (read-only) --}}
            <div>
                <label class="{{ $labelClass }}">Branch / Divisi</label>
                <div class="flex items-center gap-2.5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                    <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"/>
                    </svg>
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ $branch }}</span>
                </div>
            </div>

            {{-- Jenis Modul ERP --}}
            <div>
                <label for="erpModuleId" class="{{ $labelClass }}">
                    Jenis Modul ERP <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                    <select id="erpModuleId" wire:model="erpModuleId"
                            class="{{ $fieldClass }} appearance-none pr-10">
                        <option value="">-- Pilih modul ERP --</option>
                        @foreach($modules as $module)
                            <option value="{{ $module->id }}">{{ $module->name }}</option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
                @error('erpModuleId') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Keterangan Permintaan --}}
            <div>
                <label for="keterangan" class="{{ $labelClass }}">
                    Keterangan Permintaan <span class="text-red-400">*</span>
                </label>
                <textarea id="keterangan" wire:model="keterangan" rows="5"
                          placeholder="Deskripsikan permintaan atau kendala ERP Anda: modul yang bermasalah, langkah yang sudah dicoba, data yang terpengaruh, deadline, dll..."
                          class="{{ $fieldClass }} resize-none leading-relaxed"></textarea>
                @error('keterangan') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Lampiran --}}
            <div>
                <label class="{{ $labelClass }}">
                    Lampiran <span class="ml-1 font-normal normal-case text-slate-400">(opsional · maks. 10 MB)</span>
                </label>

                @if(count($attachments) > 0)
                    <div class="mb-2 space-y-2">
                        @foreach($attachments as $index => $file)
                            <div class="flex items-center justify-between gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                                <div class="flex min-w-0 items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
                                    </svg>
                                    <span class="truncate text-xs text-slate-600 dark:text-slate-300">{{ $file->getClientOriginalName() }}</span>
                                </div>
                                <button type="button" wire:click="removeAttachment({{ $index }})"
                                        class="shrink-0 text-red-400 transition hover:text-red-600">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:hover:border-blue-600 dark:hover:bg-blue-900/20">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                    </svg>
                    <span class="text-sm text-gray-400">Tambah File / Screenshot</span>
                    <input type="file" wire:model="attachments" multiple class="hidden">
                </label>
                @error('attachments.*') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            </div>{{-- /white card --}}
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         FIXED BOTTOM SUBMIT BUTTON
    ════════════════════════════════════════════ --}}
    <div class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] border-t border-gray-200 bg-white px-5 pb-safe-bottom pt-4 pb-8 dark:border-gray-800 dark:bg-gray-900">
        <button type="button"
                wire:click="submit"
                wire:loading.attr="disabled"
                class="w-full rounded-2xl bg-blue-600 py-4 text-sm font-bold text-white transition active:bg-blue-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="submit">Kirim Permintaan ERP</span>
            <span wire:loading wire:target="submit">Mengirim...</span>
        </button>
    </div>

    <x-filament-actions::modals />
</div>
