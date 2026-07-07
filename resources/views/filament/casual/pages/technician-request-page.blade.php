@php
    $user      = auth()->user();
    $hasBranch = (bool) $user->branch_id;
    $branch    = $user->branch?->name ?? null;
@endphp
<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
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

        <p class="text-blue-200">{{ $branch ?? 'Tanpa Cabang' }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        @php $fieldClass = 'w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-slate-700 placeholder-slate-300 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200'; @endphp
        @php $labelClass = 'mb-1.5 block text-xs font-semibold text-slate-600'; @endphp

        <div class="flex flex-col gap-4 px-5">
            <div class="flex flex-col gap-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">

            {{-- Branch / Divisi (read-only) --}}
            <div>
                <label class="{{ $labelClass }}">Branch / Divisi</label>
                @if($hasBranch)
                    <div class="flex items-center gap-2.5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                        <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"/>
                        </svg>
                        <span class="text-sm text-slate-600 dark:text-slate-300">{{ $branch }}</span>
                    </div>
                @else
                    <div class="flex items-center gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-900/20">
                        <svg class="h-4 w-4 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                        <span class="text-sm text-amber-700 dark:text-amber-400">Cabang belum diatur. Hubungi admin.</span>
                    </div>
                @endif
            </div>

            {{-- Tanggal Jadwal --}}
            <div>
                <label class="{{ $labelClass }}">Tanggal Jadwal</label>
                <input type="date" wire:model="scheduledDate"
                       min="{{ now()->toDateString() }}"
                       class="{{ $fieldClass }}">
                @error('scheduledDate') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Deskripsi Masalah --}}
            <div>
                <label class="{{ $labelClass }}">Deskripsi Masalah</label>
                <textarea wire:model="requestorNotes" rows="4"
                          placeholder="Contoh: AC di ruang meeting bocor dan tidak dingin, sudah berlangsung 2 hari. Mohon segera ditangani karena mengganggu operasional..."
                          class="{{ $fieldClass }} resize-none"></textarea>
                @error('requestorNotes') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Foto Lampiran --}}
            <div>
                <label class="{{ $labelClass }}">
                    Foto Lampiran <span class="ml-1 font-normal normal-case text-slate-400">(opsional, maks. 5 MB)</span>
                </label>

                @if(count($attachments) > 0)
                    <div class="mb-2 grid grid-cols-3 gap-2">
                        @foreach($attachments as $index => $attachment)
                            <div class="relative aspect-square">
                                <img src="{{ $attachment->temporaryUrl() }}"
                                     class="h-full w-full rounded-xl object-cover border border-gray-200">
                                <button wire:click="removeAttachment({{ $index }})" type="button"
                                        class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:hover:border-blue-600 dark:hover:bg-blue-900/20">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                    </svg>
                    <span class="text-sm text-gray-400">Tambah foto</span>
                    <input type="file" wire:model="attachments" multiple accept="image/*" class="hidden">
                </label>
                @error('attachments.*') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            </div>{{-- /white card --}}

            {{-- Submit --}}
            <button wire:click="submit" wire:loading.attr="disabled"
                    @disabled(!$hasBranch)
                    class="w-full rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">Kirim Permintaan</span>
                <span wire:loading wire:target="submit">Mengirim...</span>
            </button>

        </div>
    </div>

    <x-technician-request.bottom-nav active="form" />

</div>
