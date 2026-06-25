<div class="flex flex-col bg-sky-600 dark:bg-sky-900"
     style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Request Teknisi</span>
        </div>

        <p class="text-sky-200">{{ auth()->user()->branch?->name ?? auth()->user()->name }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        <div class="flex flex-col gap-4 px-5">

            {{-- Tanggal Jadwal --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Tanggal Jadwal
                </label>
                <div class="px-4 py-3">
                    <input type="date" wire:model="scheduledDate"
                           min="{{ now()->toDateString() }}"
                           class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 focus:ring-0 dark:text-slate-200">
                </div>
                @error('scheduledDate')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Deskripsi Masalah --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Deskripsi Masalah
                </label>
                <div class="px-4 py-3">
                    <textarea wire:model="requestorNotes" rows="4"
                              placeholder="Jelaskan masalah atau pekerjaan yang perlu dikerjakan..."
                              class="w-full resize-none border-0 bg-transparent p-0 text-sm text-slate-700 placeholder-slate-300 focus:ring-0 dark:text-slate-200 dark:placeholder-slate-600"></textarea>
                </div>
                @error('requestorNotes')
                    <p class="border-t border-red-100 px-4 py-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Foto Lampiran --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <label class="block border-b border-gray-100 px-4 py-2.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:border-gray-800">
                    Foto Lampiran
                    <span class="ml-1 font-normal normal-case text-slate-400">(opsional, maks. 5 MB per foto)</span>
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
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-200 py-4 transition hover:border-sky-300 hover:bg-sky-50 dark:border-gray-700 dark:hover:border-sky-600 dark:hover:bg-sky-900/20">
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

            {{-- Submit --}}
            <button wire:click="submit" wire:loading.attr="disabled"
                    class="w-full rounded-2xl bg-sky-600 py-3.5 text-sm font-semibold text-white shadow-sm transition active:scale-95 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">Kirim Permintaan</span>
                <span wire:loading wire:target="submit">Mengirim...</span>
            </button>

        </div>
    </div>

    <x-technician-request.bottom-nav active="form" />

</div>
