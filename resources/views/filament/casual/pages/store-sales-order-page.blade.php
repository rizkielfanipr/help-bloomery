@php
    $user = auth()->user();
    $branches = $this->getAccessibleBranches();
    $eventTypes = $this->getEventTypes();
    $productTypes = $this->getProductTypes();
    $recentOrders = $this->getRecentOrders();

    $fieldClass = 'w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-slate-700 placeholder-slate-300 focus:border-blue-400 focus:outline-none focus:ring-0 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-200';
    $labelClass = 'mb-1.5 block text-xs font-semibold text-slate-600';
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- HEADER --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}"
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
            <span class="text-base font-semibold text-white">Store Sales Order</span>
        </div>

        <p class="text-blue-200">{{ $user->branch?->name ?? 'Tanpa Cabang' }}</p>
        <p class="text-xl font-semibold text-white">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    {{-- WHITE CONTENT CARD --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">
        <div class="flex flex-col gap-5 px-5">

        @if($submitted)
            <div class="flex flex-col items-center gap-4 rounded-2xl border border-gray-200 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 dark:bg-emerald-900/20">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Store Sales Order Berhasil Disimpan</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Nomor Sales Order:</p>
                    <p class="mt-1 text-lg font-bold text-blue-600 dark:text-blue-400">{{ $submittedNumber }}</p>
                </div>
                <div class="flex w-full gap-3">
                    <button type="button" wire:click="startNew"
                            class="flex-1 rounded-xl border border-gray-200 py-2.5 text-sm font-semibold text-slate-600 transition active:bg-gray-50 dark:border-gray-700 dark:text-slate-300">
                        Isi Lagi
                    </button>
                    <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}"
                       class="flex flex-1 items-center justify-center rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white transition active:scale-95">
                        Kembali ke Beranda
                    </a>
                </div>
            </div>
        @else

            {{-- Informasi Pengisian --}}
            <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 dark:border-blue-900 dark:bg-blue-950/30">
                <div class="flex items-start gap-2">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                    </svg>
                    <div class="text-xs leading-relaxed text-blue-700 dark:text-blue-300">
                        <p class="font-semibold">Informasi Pengisian</p>
                        <p class="mt-1">Masukkan nomor Sales Order yang sudah dibuat di ESB. Periksa kembali data customer dan tanggal yang ditemukan, lalu isi hanya kebutuhan operasional yang belum tercatat di ESB.</p>
                    </div>
                </div>
            </div>

            {{-- Referensi Sales Order --}}
            <div class="flex flex-col gap-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Referensi Sales Order</p>

                <div>
                    <label for="branchId" class="{{ $labelClass }}">Branch <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <select id="branchId" wire:model.live="branchId" class="{{ $fieldClass }} appearance-none pr-10">
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    @error('branchId') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="productSalesNumber" class="{{ $labelClass }}">Nomor Sales Order / SL <span class="text-red-400">*</span></label>
                    <div class="flex gap-2">
                        <input type="text" id="productSalesNumber" wire:model.live="productSalesNumber" maxlength="100"
                               placeholder="Contoh: SL-00123" class="{{ $fieldClass }}">
                    </div>
                    @error('productSalesNumber') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                @if(! $lookupResult)
                    <button type="button" wire:click="lookupEsb" wire:loading.attr="disabled" wire:target="lookupEsb"
                            @disabled($isLookingUp || $branchId === null || trim($productSalesNumber) === '')
                            class="w-full rounded-xl bg-slate-900 py-3 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900">
                        <span wire:loading.remove wire:target="lookupEsb">Cari ESB</span>
                        <span wire:loading wire:target="lookupEsb">Mencari...</span>
                    </button>
                @else
                    {{-- Kartu konfirmasi read-only --}}
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Ditemukan di ESB</p>
                            <button type="button" wire:click="clearLookup" class="text-xs font-semibold text-emerald-700 underline dark:text-emerald-300">
                                Cari Ulang
                            </button>
                        </div>
                        <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                            <div><dt class="text-slate-500">Nomor SO</dt><dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $lookupResult['product_sales_number'] }}</dd></div>
                            <div><dt class="text-slate-500">Branch ESB</dt><dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $lookupResult['branch_name'] ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Tanggal SO</dt><dd class="text-slate-700 dark:text-slate-200">{{ $lookupResult['product_sales_date'] ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Required Date</dt><dd class="text-slate-700 dark:text-slate-200">{{ $lookupResult['required_date'] ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Customer</dt><dd class="text-slate-700 dark:text-slate-200">{{ $lookupResult['customer_name'] ?? '-' }}</dd></div>
                            <div><dt class="text-slate-500">Status ESB</dt><dd class="text-slate-700 dark:text-slate-200">{{ $lookupResult['status_name'] ?? '-' }}</dd></div>
                            <div class="col-span-2"><dt class="text-slate-500">Alamat</dt><dd class="text-slate-700 dark:text-slate-200">{{ $lookupResult['customer_address'] ?? '-' }}</dd></div>
                            <div class="col-span-2"><dt class="text-slate-500">Total</dt><dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $lookupResult['currency_sign'] ?? '' }} {{ $lookupResult['total'] !== null ? number_format($lookupResult['total'], 0, ',', '.') : '-' }}</dd></div>
                        </dl>
                    </div>
                @endif
            </div>

            @if($lookupResult)
                {{-- Informasi Operasional --}}
                <div class="flex flex-col gap-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Informasi Operasional</p>

                    <div>
                        <label for="phoneNumber" class="{{ $labelClass }}">Nomor HP <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span></label>
                        <input type="text" id="phoneNumber" wire:model="phoneNumber" maxlength="50" class="{{ $fieldClass }}">
                        @error('phoneNumber') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="orderedBy" class="{{ $labelClass }}">Order By <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span></label>
                        <input type="text" id="orderedBy" wire:model="orderedBy" maxlength="150" class="{{ $fieldClass }}">
                        @error('orderedBy') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="eventType" class="{{ $labelClass }}">Jenis Acara <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span></label>
                        <div class="relative">
                            <select id="eventType" wire:model="eventType" class="{{ $fieldClass }} appearance-none pr-10">
                                <option value="">-- Pilih jenis acara --</option>
                                @foreach($eventTypes as $eventTypeOption)
                                    <option value="{{ $eventTypeOption->value }}">{{ $eventTypeOption->getLabel() }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                                </svg>
                            </div>
                        </div>
                        @error('eventType') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    @if($eventType === \App\Enums\StoreSalesOrderEventType::Other->value)
                        <div>
                            <label for="eventTypeOther" class="{{ $labelClass }}">Keterangan Acara Lainnya</label>
                            <input type="text" id="eventTypeOther" wire:model="eventTypeOther" maxlength="150" class="{{ $fieldClass }}">
                            @error('eventTypeOther') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label for="deliveryTime" class="{{ $labelClass }}">Jam Pengiriman <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span></label>
                        <input type="time" id="deliveryTime" wire:model="deliveryTime" class="{{ $fieldClass }}">
                        @error('deliveryTime') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="preparationNotes" class="{{ $labelClass }}">Catatan Persiapan <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span></label>
                        <textarea id="preparationNotes" wire:model="preparationNotes" rows="4" maxlength="2000"
                                  class="{{ $fieldClass }} resize-none leading-relaxed"></textarea>
                        @error('preparationNotes') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Kebutuhan Produk --}}
                <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Kebutuhan Produk</p>

                    @foreach($items as $index => $item)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Baris {{ $index + 1 }}</span>
                                @if(count($items) > 1)
                                    <button type="button" wire:click="removeItem({{ $index }})" class="text-xs font-semibold text-red-500">Hapus</button>
                                @endif
                            </div>

                            <div class="flex flex-col gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">Pilihan Produk <span class="text-red-400">*</span></label>
                                    <div class="relative">
                                        <select wire:model="items.{{ $index }}.product_type" class="{{ $fieldClass }} appearance-none pr-10">
                                            <option value="">-- Pilih produk --</option>
                                            @foreach($productTypes as $productTypeOption)
                                                <option value="{{ $productTypeOption->value }}">{{ $productTypeOption->getLabel() }}</option>
                                            @endforeach
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </div>
                                    @error('items.'.$index.'.product_type') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>

                                @if(($item['product_type'] ?? '') === \App\Enums\StoreSalesOrderProductType::Custom->value)
                                    <div>
                                        <label class="{{ $labelClass }}">Detail Custom <span class="text-red-400">*</span></label>
                                        <input type="text" wire:model="items.{{ $index }}.custom_detail" maxlength="500" class="{{ $fieldClass }}">
                                        @error('items.'.$index.'.custom_detail') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                @endif

                                <div>
                                    <label class="{{ $labelClass }}">Jumlah <span class="text-red-400">*</span></label>
                                    <input type="number" step="0.01" min="0" wire:model="items.{{ $index }}.quantity" class="{{ $fieldClass }}">
                                    @error('items.'.$index.'.quantity') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="{{ $labelClass }}">Catatan <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span></label>
                                    <input type="text" wire:model="items.{{ $index }}.notes" maxlength="500" class="{{ $fieldClass }}">
                                    @error('items.'.$index.'.notes') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <button type="button" wire:click="addItem" class="w-full rounded-xl border border-dashed border-gray-300 py-2.5 text-sm font-semibold text-blue-600 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:hover:bg-blue-900/20">
                        + Tambah Baris
                    </button>
                </div>

                {{-- Attachment --}}
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <label class="{{ $labelClass }}">
                        Attachment <span class="ml-1 font-normal normal-case text-slate-400">(opsional &middot; maks. 5 file, 5 MB/file)</span>
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
                                @error('attachments.'.$index) <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            @endforeach
                        </div>
                    @endif

                    @if(count($attachments) < 5)
                        <label wire:loading.class="opacity-50" wire:target="attachments"
                               class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:hover:border-blue-600 dark:hover:bg-blue-900/20">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                            </svg>
                            <span class="text-sm text-gray-400" wire:loading.remove wire:target="attachments">Tambah Foto / Dokumen</span>
                            <span class="text-sm text-gray-400" wire:loading wire:target="attachments">Mengunggah...</span>
                            <input type="file" wire:model="attachments" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="hidden">
                        </label>
                    @endif
                    @error('attachments') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Submit button --}}
                <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                        @disabled($isSubmitting)
                        class="w-full rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">
                    <span wire:loading.remove wire:target="submit">Simpan Store Sales Order</span>
                    <span wire:loading wire:target="submit">Menyimpan...</span>
                </button>
            @endif

            {{-- Riwayat terbaru --}}
            @if($recentOrders->isNotEmpty())
                <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Store Sales Order Terakhir</p>
                    @foreach($recentOrders as $recent)
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-3 py-2.5 dark:border-gray-700">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $recent->product_sales_number }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $recent->customer_name_snapshot ?? '-' }} &middot; {{ $recent->branch?->name ?? '-' }}
                                </p>
                                @if($recent->required_date)
                                    <p class="text-xs text-slate-400">Required: {{ $recent->required_date->translatedFormat('d M Y') }}</p>
                                @endif
                            </div>
                            <span @class([
                                'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-gray-100 text-gray-600' => $recent->operational_status->getColor() === 'gray',
                                'bg-sky-100 text-sky-700' => $recent->operational_status->getColor() === 'info',
                                'bg-amber-100 text-amber-700' => $recent->operational_status->getColor() === 'warning',
                                'bg-emerald-100 text-emerald-700' => $recent->operational_status->getColor() === 'success',
                                'bg-red-100 text-red-700' => $recent->operational_status->getColor() === 'danger',
                            ])>
                                {{ $recent->operational_status->getLabel() }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

        @endif

        </div>
    </div>

    <x-filament-actions::modals />
</div>
