@php
    $user        = auth()->user();
    $hasBranch   = (bool) $user->branch_id;
    $branch      = $user->branch?->name ?? null;
    $formOpen    = $this->isFormOpen();
    $closeReason = $this->getCloseReason();
@endphp
<div class="flex flex-col bg-blue-600 dark:bg-blue-900"
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

        @if(! $formOpen)
            {{-- Form Closed Notice --}}
            <div class="flex flex-col items-center gap-3 rounded-2xl border border-red-200 bg-red-50 p-6 text-center dark:border-red-800 dark:bg-red-900/20">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                    <svg class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-red-700 dark:text-red-400">Form Pengajuan Ditutup</p>
                    @if($closeReason)
                        <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $closeReason }}</p>
                    @endif
                </div>
            </div>
        @elseif($submitted)

            <x-casual.whatsapp-success-card
                title="Pengajuan pembelian berhasil dikirim!"
                subtitle="Supaya lebih cepat diproses, konfirmasikan juga ke tim purchasing lewat WhatsApp."
                :whatsapp-url="$whatsappUrl"
                :code="$requestCode"
                cta-label="Kirim ke WhatsApp Purchasing"
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

            {{-- Item Name --}}
            <div>
                <label class="{{ $labelClass }}">Nama Barang</label>
                <input type="text" wire:model="itemName"
                       placeholder="Contoh: Laptop ASUS VivoBook, Meja Kerja..."
                       class="{{ $fieldClass }}">
                @error('itemName') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Quantity --}}
            <div>
                <label class="{{ $labelClass }}">Jumlah Barang</label>
                <input type="number" wire:model="quantity" min="1"
                       placeholder="1"
                       class="{{ $fieldClass }}">
                @error('quantity') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Purchase Reason --}}
            <div>
                <label class="{{ $labelClass }}">Alasan Pembelian</label>
                <textarea wire:model="purchaseReason" rows="3"
                          placeholder="Contoh: Laptop lama sudah rusak dan tidak bisa diperbaiki, dibutuhkan untuk pekerjaan sehari-hari..."
                          class="{{ $fieldClass }} resize-none"></textarea>
                @error('purchaseReason') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Purchase Type --}}
            <div>
                <label class="{{ $labelClass }}">Jenis Pembelian</label>
                <div class="relative">
                    <select wire:model.live="purchaseType" class="{{ $fieldClass }} appearance-none pr-10">
                        <option value="">-- Pilih jenis pembelian --</option>
                        <option value="new">New Purchase</option>
                        <option value="broken">Replacement (Broken)</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
                @error('purchaseType') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Journal Item Number (conditional) --}}
            @if($purchaseType === 'broken')
                <div>
                    <label class="{{ $labelClass }}">Nomor Item Journal</label>
                    <input type="text" wire:model="journalItemNumber"
                           placeholder="Contoh: JRN-0001"
                           class="{{ $fieldClass }}">
                    @error('journalItemNumber') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
            @endif

            {{-- Purchase Request Number --}}
            <div>
                <label class="{{ $labelClass }}">
                    Nomor Purchase Request
                    <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span>
                </label>
                <input type="text" wire:model="purchaseRequestNumber"
                       placeholder="Contoh: PR-2025-001"
                       class="{{ $fieldClass }}">
                @error('purchaseRequestNumber') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- E-Commerce Link --}}
            <div>
                <label class="{{ $labelClass }}">
                    Link e-Commerce
                    <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span>
                </label>
                <input type="url" wire:model="ecommerceLink"
                       placeholder="https://shopee.co.id/..."
                       class="{{ $fieldClass }}">
                @error('ecommerceLink') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Attachments --}}
            <div>
                <label class="{{ $labelClass }}">
                    Foto / Lampiran
                    <span class="ml-1 font-normal normal-case text-slate-400">(opsional, maks. 5 MB)</span>
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

            {{-- Submit button --}}
            <button wire:click="submit" wire:loading.attr="disabled"
                    @disabled(!$hasBranch)
                    class="w-full rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">Kirim Pengajuan</span>
                <span wire:loading wire:target="submit">Mengirim...</span>
            </button>

        @endif

        </div>
    </div>

    {{-- ════════════════════════════════════════════
         BOTTOM NAV
    ════════════════════════════════════════════ --}}
    <x-purchase-request.bottom-nav active="form" />

</div>
