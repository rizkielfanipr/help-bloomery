@php
    $user       = auth()->user();
    $hasBranch  = (bool) $user->branch_id;
    $branch     = $user->branch?->name ?? null;
    $platforms  = $this->getPlatforms();
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

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
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Request Konten</span>
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

        <div class="px-5">
        @if($submitted)

            <x-casual.whatsapp-success-card
                title="Permintaan konten berhasil dikirim!"
                subtitle="Supaya lebih cepat diproses, konfirmasikan juga ke tim design lewat WhatsApp."
                :whatsapp-url="$whatsappUrl"
                :code="$requestCode"
                cta-label="Kirim ke WhatsApp Designer"
            />

        @else
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

            {{-- Judul Konten --}}
            <div>
                <label for="judulKonten" class="{{ $labelClass }}">
                    Judul Konten <span class="text-red-400">*</span>
                </label>
                <input id="judulKonten" type="text" wire:model="judulKonten"
                       placeholder="Contoh: Video Promo Menu Baru..."
                       class="{{ $fieldClass }}">
                @error('judulKonten') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Jenis Konten --}}
            <div>
                <label for="jenisKonten" class="{{ $labelClass }}">
                    Jenis Konten <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                    <select id="jenisKonten" wire:model="jenisKonten"
                            class="{{ $fieldClass }} appearance-none pr-10">
                        <option value="">-- Pilih jenis konten --</option>
                        <option value="photo">Konten Foto</option>
                        <option value="video">Konten Video</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
                @error('jenisKonten') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Platform Tujuan --}}
            <div>
                <label for="platformTujuan" class="{{ $labelClass }}">
                    Platform Tujuan <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                    <select id="platformTujuan" wire:model="platformTujuan"
                            class="{{ $fieldClass }} appearance-none pr-10">
                        <option value="">-- Pilih platform --</option>
                        @foreach($platforms as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
                @error('platformTujuan') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Tujuan Konten --}}
            <div>
                <label for="tujuanKonten" class="{{ $labelClass }}">
                    Tujuan Konten <span class="text-red-400">*</span>
                </label>
                <textarea id="tujuanKonten" wire:model="tujuanKonten" rows="5"
                          placeholder="Deskripsikan tujuan konten Anda: pesan yang ingin disampaikan, target audience, gaya/mood, deadline, dll..."
                          class="{{ $fieldClass }} resize-none leading-relaxed"></textarea>
                @error('tujuanKonten') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Link Contoh Konten --}}
            <div>
                <label for="linkContohKonten" class="{{ $labelClass }}">
                    Link Contoh Konten
                    <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span>
                </label>
                <input id="linkContohKonten" type="url" wire:model="linkContohKonten"
                       placeholder="https://instagram.com/reel/..."
                       class="{{ $fieldClass }}">
                @error('linkContohKonten') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
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
                    <span class="text-sm text-gray-400">Tambah File / Referensi</span>
                    <input type="file" wire:model="attachments" multiple class="hidden">
                </label>
                @error('attachments.*') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            </div>{{-- /white card --}}

            {{-- Submit button --}}
            <button type="button" wire:click="submit" wire:loading.attr="disabled"
                    @disabled(!$hasBranch)
                    class="w-full rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">Kirim Permintaan Konten</span>
                <span wire:loading wire:target="submit">Mengirim...</span>
            </button>

        @endif
        </div>
    </div>

    <x-content-request.bottom-nav active="form" />

    <x-filament-actions::modals />
</div>
