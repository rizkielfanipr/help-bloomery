@php
    $user = auth()->user();
    $branches = $this->getAccessibleBranches();
    $sources = $this->getSources();
    $categories = $this->getCategories();

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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Form Komplain</span>
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
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Komplain Berhasil Dikirim</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Nomor komplain Anda:</p>
                    <p class="mt-1 text-lg font-bold text-blue-600 dark:text-blue-400">{{ $submittedNumber }}</p>
                </div>
                <div class="flex w-full gap-3">
                    <button type="button" wire:click="startNewComplaint"
                            class="flex-1 rounded-xl border border-gray-200 py-2.5 text-sm font-semibold text-slate-600 transition active:bg-gray-50 dark:border-gray-700 dark:text-slate-300">
                        Isi Lagi
                    </button>
                    <a href="{{ route('filament.casual.pages.customer-complaint-history-page') }}"
                       class="flex flex-1 items-center justify-center rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white transition active:scale-95">
                        Lihat Detail
                    </a>
                </div>
                <a href="{{ \App\Filament\Casual\Pages\LauncherPage::getUrl() }}" class="text-xs font-medium text-slate-400 underline">
                    Kembali ke Beranda
                </a>
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
                        <p class="mt-1">Catat komplain sesuai informasi yang diterima customer. Pilih Branch tempat kejadian dan jelaskan kronologi secara singkat. Lampirkan foto atau bukti transaksi jika tersedia.</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">

                {{-- Branch --}}
                <div>
                    <label for="branchId" class="{{ $labelClass }}">Branch <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <select id="branchId" wire:model="branchId" class="{{ $fieldClass }} appearance-none pr-10">
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

                {{-- Tanggal Kejadian --}}
                <div>
                    <label for="occurredAt" class="{{ $labelClass }}">Tanggal Kejadian <span class="text-red-400">*</span></label>
                    <input type="date" id="occurredAt" wire:model="occurredAt" max="{{ now()->format('Y-m-d') }}" class="{{ $fieldClass }}">
                    @error('occurredAt') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Sumber Komplain --}}
                <div>
                    <label for="source" class="{{ $labelClass }}">Sumber Komplain <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <select id="source" wire:model="source" class="{{ $fieldClass }} appearance-none pr-10">
                            <option value="">-- Pilih sumber komplain --</option>
                            @foreach($sources as $sourceOption)
                                <option value="{{ $sourceOption->value }}">{{ $sourceOption->getLabel() }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    @error('source') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Kategori Komplain --}}
                <div>
                    <label for="category" class="{{ $labelClass }}">Kategori Komplain <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <select id="category" wire:model="category" class="{{ $fieldClass }} appearance-none pr-10">
                            <option value="">-- Pilih kategori komplain --</option>
                            @foreach($categories as $categoryOption)
                                <option value="{{ $categoryOption->value }}">{{ $categoryOption->getLabel() }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    @error('category') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Nomor Pesanan / Struk --}}
                <div>
                    <label for="orderReference" class="{{ $labelClass }}">Nomor Pesanan / Struk <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span></label>
                    <input type="text" id="orderReference" wire:model="orderReference" maxlength="100" placeholder="Contoh: TRX-000123" class="{{ $fieldClass }}">
                    @error('orderReference') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Nama Customer --}}
                <div>
                    <label for="customerName" class="{{ $labelClass }}">Nama Customer <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span></label>
                    <input type="text" id="customerName" wire:model="customerName" maxlength="150" class="{{ $fieldClass }}">
                    @error('customerName') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Kontak Customer --}}
                <div>
                    <label for="customerContact" class="{{ $labelClass }}">Kontak Customer <span class="ml-1 font-normal normal-case text-slate-400">(opsional)</span></label>
                    <input type="text" id="customerContact" wire:model="customerContact" maxlength="100" placeholder="Nomor HP atau akun media sosial" class="{{ $fieldClass }}">
                    @error('customerContact') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Detail Komplain --}}
                <div>
                    <label for="description" class="{{ $labelClass }}">Detail Komplain <span class="text-red-400">*</span></label>
                    <textarea id="description" wire:model="description" rows="5" maxlength="2000"
                              placeholder="Jelaskan kronologi komplain secara singkat..."
                              class="{{ $fieldClass }} resize-none leading-relaxed"></textarea>
                    @error('description') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Attachment --}}
                <div>
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
                            <span class="text-sm text-gray-400" wire:loading.remove wire:target="attachments">Tambah Foto / Bukti</span>
                            <span class="text-sm text-gray-400" wire:loading wire:target="attachments">Mengunggah...</span>
                            <input type="file" wire:model="attachments" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="hidden">
                        </label>
                    @endif
                    @error('attachments') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

            </div>

            {{-- Submit button --}}
            <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                    @disabled($isSubmitting)
                    class="w-full rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white transition active:scale-95 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">Kirim Komplain</span>
                <span wire:loading wire:target="submit">Mengirim...</span>
            </button>

        @endif

        </div>
    </div>

    <x-customer-complaint.bottom-nav active="form" />

    <x-filament-actions::modals />
</div>
