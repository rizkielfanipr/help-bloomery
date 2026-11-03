@php
    $user = auth()->user();
    $hasBranch = (bool) $user->branch_id;
    $branch = $user->branch?->name ?? null;
    $fieldClass = 'w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-slate-700 placeholder-slate-300 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200';
    $labelClass = 'mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-300';
    $keptCount = count($storedAttachments) - count($removedAttachmentPaths);
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- Header --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}" aria-label="Kembali ke launcher"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <x-heroicon-o-magnifying-glass-circle class="h-5 w-5" />
            </div>
            <span class="text-base font-semibold text-white">Request Sourcing</span>
        </div>
        <p class="text-blue-200">{{ $branch ?? 'Tanpa Cabang' }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- Content --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">
        <div class="px-5">
        @if($submitted)
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                    <x-heroicon-o-check class="h-7 w-7 text-emerald-600 dark:text-emerald-400" />
                </div>
                <p class="mt-4 text-base font-semibold text-slate-800 dark:text-white">Request Sourcing terkirim</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Purchasing akan memeriksa kebutuhan Anda dan mencarikan supplier.</p>
                <p class="mt-4 font-mono text-sm font-bold text-blue-600 dark:text-blue-400">{{ $requestNumber }}</p>
                <span class="mt-2 inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ $submittedStatusLabel }}</span>
                <div class="mt-6 flex flex-col gap-2">
                    <a href="{{ \App\Filament\Casual\Pages\MaterialSourcingRequestHistoryPage::getUrl(panel: 'casual') }}" class="w-full rounded-2xl bg-blue-600 py-3 text-sm font-semibold text-white">Lihat Riwayat</a>
                    <button type="button" wire:click="startNewRequest" class="w-full rounded-2xl border border-gray-200 py-3 text-sm font-semibold text-slate-600 dark:border-gray-700 dark:text-slate-300">Buat Request Lain</button>
                </div>
            </div>
        @else
            <div class="flex flex-col gap-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">

                @if($editingRequestId)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-900/20">
                        <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">Perbaikan diminta Purchasing</p>
                        <p class="mt-1 whitespace-pre-line break-words text-xs text-amber-700 dark:text-amber-400">{{ $revisionReason }}</p>
                    </div>
                @endif

                {{-- Branch --}}
                <div>
                    <span class="{{ $labelClass }}">Branch / Divisi</span>
                    @if($hasBranch)
                        <div class="flex items-center gap-2.5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                            <x-heroicon-o-building-office-2 class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="text-sm text-slate-600 dark:text-slate-300">{{ $branch }}</span>
                        </div>
                    @else
                        <div class="flex items-center gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-900/20">
                            <x-heroicon-o-exclamation-triangle class="h-4 w-4 shrink-0 text-amber-500" />
                            <span class="text-sm text-amber-700 dark:text-amber-400">Cabang belum diatur. Hubungi admin.</span>
                        </div>
                    @endif
                    @error('branch') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Info --}}
                <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 dark:border-blue-900 dark:bg-blue-950/30">
                    <div class="flex items-start gap-2">
                        <x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" />
                        <div class="text-xs leading-relaxed text-blue-700 dark:text-blue-300">
                            <p class="font-semibold">Informasi pengisian</p>
                            <p class="mt-1">Satu request untuk satu produk yang sudah ada di Master Product ESB. Supplier, harga, dan sample akan dicarikan Purchasing.</p>
                        </div>
                    </div>
                </div>

                {{-- Product --}}
                <div>
                    <span class="{{ $labelClass }}">Produk <span class="text-red-400">*</span></span>
                    @if($selectedProduct)
                        <div class="rounded-xl border border-blue-200 bg-blue-50/60 p-4 dark:border-blue-900 dark:bg-blue-950/20">
                            <p class="break-words text-sm font-semibold text-slate-800 dark:text-white">{{ $selectedProduct['productName'] }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><span class="font-mono text-blue-600 dark:text-blue-400">{{ $selectedProduct['productCode'] }}</span> · {{ $selectedProduct['unit'] ?: '-' }}</p>
                            @if(filled($selectedProduct['categoryName']) || filled($selectedProduct['subCategoryName']))
                                <p class="mt-1 text-xs text-slate-400">{{ collect([$selectedProduct['categoryName'], $selectedProduct['subCategoryName']])->filter()->implode(' · ') }}</p>
                            @endif
                            <button type="button" wire:click="openProductPicker" class="mt-3 inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-white px-3 py-2 text-xs font-semibold text-blue-600 dark:border-blue-800 dark:bg-gray-900 dark:text-blue-300">
                                <x-heroicon-o-arrow-path class="h-4 w-4" /> Ganti Produk
                            </button>
                        </div>
                    @else
                        <button type="button" wire:click="openProductPicker" class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-blue-300 py-4 text-sm font-semibold text-blue-600 transition hover:bg-blue-50 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-950/20">
                            <x-heroicon-o-plus-circle class="h-5 w-5" /> Tambah Produk
                        </button>
                    @endif
                    @if($loadError)<p class="mt-1.5 text-xs text-red-500">Master Product ESB belum dapat dimuat. Coba lagi beberapa saat.</p>@endif
                    @error('productCode') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                    @error('productDetailId') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Reason --}}
                <div>
                    <label for="msr-reason" class="{{ $labelClass }}">Alasan / Tujuan <span class="text-red-400">*</span></label>
                    <textarea id="msr-reason" wire:model="reason" rows="4" maxlength="2000" placeholder="Contoh: supplier lama berhenti produksi, butuh alternatif dengan kualitas setara." class="{{ $fieldClass }} resize-none leading-relaxed"></textarea>
                    @error('reason') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Quantity --}}
                <div>
                    <label for="msr-qty" class="{{ $labelClass }}">Estimasi Kebutuhan <span class="font-normal text-slate-400">(opsional)</span></label>
                    <div class="flex items-center gap-2">
                        <input id="msr-qty" type="number" inputmode="decimal" step="any" min="0" wire:model="estimatedQuantity" placeholder="0" class="{{ $fieldClass }}">
                        <span class="shrink-0 rounded-xl bg-gray-100 px-3 py-3 text-xs font-semibold text-slate-500 dark:bg-gray-800 dark:text-slate-300">{{ $selectedProduct['unit'] ?? 'UOM' }}</span>
                    </div>
                    @error('estimatedQuantity') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Target date --}}
                <div>
                    <label for="msr-target" class="{{ $labelClass }}">Target Tanggal <span class="font-normal text-slate-400">(opsional)</span></label>
                    <input id="msr-target" type="date" min="{{ today()->toDateString() }}" wire:model="targetDate" class="{{ $fieldClass }}">
                    @error('targetDate') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Notes --}}
                <div>
                    <label for="msr-notes" class="{{ $labelClass }}">Catatan Tambahan <span class="font-normal text-slate-400">(opsional)</span></label>
                    <textarea id="msr-notes" wire:model="notes" rows="3" maxlength="2000" placeholder="Spesifikasi, merek yang pernah dipakai, atau info lain." class="{{ $fieldClass }} resize-none leading-relaxed"></textarea>
                    @error('notes') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Attachments --}}
                <div>
                    <span class="{{ $labelClass }}">Lampiran <span class="font-normal text-slate-400">(opsional · maks. {{ \App\Models\MaterialSourcingRequest::MAX_ATTACHMENTS }} file · 10 MB · JPG/PNG/WEBP/PDF)</span></span>

                    @if($storedAttachments !== [] || $attachments !== [])
                        <div class="mb-2 space-y-2">
                            @foreach($storedAttachments as $stored)
                                @php $isRemoved = in_array($stored['path'], $removedAttachmentPaths, true); @endphp
                                <div wire:key="stored-{{ md5($stored['path']) }}" class="flex items-center justify-between gap-2 rounded-xl border px-3 py-2 {{ $isRemoved ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/20' : 'border-gray-200 dark:border-gray-700' }}">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <x-heroicon-o-paper-clip class="h-4 w-4 shrink-0 text-blue-400" />
                                        <span class="truncate text-xs {{ $isRemoved ? 'text-red-500 line-through' : 'text-slate-600 dark:text-slate-300' }}">{{ $stored['name'] }}</span>
                                    </span>
                                    <button type="button" wire:click="toggleStoredAttachment(@js($stored['path']))" class="shrink-0 text-xs font-semibold {{ $isRemoved ? 'text-blue-600' : 'text-red-500' }}">{{ $isRemoved ? 'Batal Hapus' : 'Hapus' }}</button>
                                </div>
                            @endforeach
                            @foreach($attachments as $index => $file)
                                <div wire:key="new-attachment-{{ $index }}" class="flex items-center justify-between gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <x-heroicon-o-paper-clip class="h-4 w-4 shrink-0 text-blue-400" />
                                        <span class="truncate text-xs text-slate-600 dark:text-slate-300">{{ $file->getClientOriginalName() }}</span>
                                    </span>
                                    <button type="button" wire:click="removeAttachment({{ $index }})" aria-label="Hapus lampiran {{ $file->getClientOriginalName() }}" class="shrink-0 text-red-400 transition hover:text-red-600">
                                        <x-heroicon-o-x-mark class="h-4 w-4" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($keptCount + count($attachments) < \App\Models\MaterialSourcingRequest::MAX_ATTACHMENTS)
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:hover:border-blue-600 dark:hover:bg-blue-900/20">
                            <x-heroicon-o-arrow-up-tray class="h-5 w-5 text-gray-400" />
                            <span class="text-sm text-gray-400" wire:loading.remove wire:target="attachments">Tambah Foto / Dokumen</span>
                            <span class="text-sm text-blue-500" wire:loading wire:target="attachments">Mengunggah...</span>
                            <input type="file" wire:model="attachments" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="hidden">
                        </label>
                    @endif
                    @error('attachments') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                    @error('attachments.*') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                    @error('submissionKey') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit,attachments" @disabled(! $hasBranch)
                    class="mt-4 w-full rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">{{ $editingRequestId ? 'Kirim Ulang Request' : 'Kirim Request Sourcing' }}</span>
                <span wire:loading wire:target="submit">Mengirim...</span>
            </button>
        @endif
        </div>
    </div>

    @include('filament.casual.pages.partials.esb-product-picker-sheet', ['pickerDescription' => 'Master Product ESB aktif untuk Request Sourcing.', 'pickerSelectMethod' => 'selectProduct', 'pickerShowsStock' => false])

    <x-material-sourcing-request.bottom-nav active="form" />
</div>
