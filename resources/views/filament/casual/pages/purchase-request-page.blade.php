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

        <p class="text-blue-200">{{ auth()->user()->branch?->name ?? 'Tanpa Cabang' }}</p>
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

            {{-- Division --}}
            <div>
                <label class="{{ $labelClass }}">Divisi</label>
                <input type="text" wire:model="division"
                       placeholder="Contoh: Marketing, Operasional, HRD..."
                       class="{{ $fieldClass }}">
                @error('division') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
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
                        <option value="new">Baru</option>
                        <option value="broken">Rusak / Penggantian</option>
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
                    class="w-full rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">
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
