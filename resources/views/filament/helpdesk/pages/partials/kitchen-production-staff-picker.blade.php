{{-- Staff Produksi picker (docs/products/kitchen/production-staff-assignment/prd.md §17.2). Data comes from the
     page's computed `staffPicker`; this view runs no query and changes nothing itself. --}}
@php
    $staffPicker = $this->staffPicker;
    $checkboxClass = 'h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800';
@endphp
<div id="kitchen-staff-picker" x-data x-trap.inert="true" x-on:keydown.escape.window="$wire.closeStaffPicker()" class="fixed inset-0 z-[140] flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="kitchen-staff-picker-title">
    <button type="button" wire:click="closeStaffPicker" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup pilihan Staff Produksi" tabindex="-1"></button>
    @if(! $staffPicker)
        <x-rnd.picker-modal title="Pilih Staff Produksi" maxWidth="2xl">
            <x-slot:close><button type="button" wire:click="closeStaffPicker" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup"><x-heroicon-o-x-mark class="h-5 w-5" /></button></x-slot:close>
            <h4 id="kitchen-staff-picker-title" class="sr-only">Pilih Staff Produksi</h4>
            <p class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">Anda tidak lagi dapat mengatur Staff Produksi untuk detail ini.</p>
        </x-rnd.picker-modal>
    @else
        @php
            $staffDetail = $staffPicker['detail'];
            $staffOrder = $staffDetail->order;
        @endphp
        <x-rnd.picker-modal title="Pilih Staff Produksi" :description="$staffOrder->production_order_num.' · '.($staffDetail->bom_name ?? $staffDetail->product_name ?? '-')" maxWidth="2xl">
            <x-slot:close><button type="button" wire:click="closeStaffPicker" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Tutup pilihan Staff Produksi"><x-heroicon-o-x-mark class="h-5 w-5" /></button></x-slot:close>
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                <h4 id="kitchen-staff-picker-title" class="sr-only">Pilih Staff Produksi untuk {{ $staffDetail->bom_name ?? $staffDetail->product_name }}</h4>

                <dl class="grid gap-2 rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-700 sm:grid-cols-2">
                    <div><dt class="text-xs text-gray-500">Production Order</dt><dd class="break-words text-gray-900 dark:text-white">{{ $staffOrder->production_order_num }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Branch</dt><dd class="break-words text-gray-900 dark:text-white">{{ $staffOrder->branch->name }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-500">BOM / Produk</dt><dd class="break-words text-gray-900 dark:text-white">{{ $staffDetail->bom_name ?? '-' }}{{ $staffDetail->product_name && $staffDetail->product_name !== $staffDetail->bom_name ? ' · '.$staffDetail->product_name : '' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Quantity</dt><dd class="tabular-nums text-gray-900 dark:text-white">{{ \App\Filament\Helpdesk\Pages\KitchenProductionSchedulePage::formatQuantity($staffDetail->quantity) }} {{ $staffDetail->uom_name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Status</dt><dd class="text-gray-900 dark:text-white">{{ $staffDetail->is_finished === null ? '-' : ($staffDetail->is_finished ? 'Selesai' : 'Belum selesai') }}</dd></div>
                </dl>

                @if($staffDetail->is_finished)
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200" role="note">Detail ini sudah selesai di ESB. Koreksi Staff Produksi tetap diperbolehkan dan dicatat di riwayat perubahan.</p>
                @endif

                <div>
                    <label for="kitchen-staff-search" class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Cari nama, kode, atau posisi</label>
                    <input id="kitchen-staff-search" type="search" wire:model.live.debounce.300ms="staffSearch" maxlength="100" autocomplete="off" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Contoh: Budi, EMP0012, Cook">
                </div>

                @if($staffDetail->staffAssignments->isNotEmpty())
                    <fieldset>
                        <legend class="mb-1 text-xs font-bold uppercase tracking-wide text-gray-500">Staff saat ini</legend>
                        <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                            @foreach($staffDetail->staffAssignments as $assignment)
                                @php($staffWarning = $assignment->historyWarning($staffOrder->branch_id))
                                <li wire:key="staff-current-{{ $assignment->id }}">
                                    @if($assignment->employee_id === null)
                                        <div class="flex items-start gap-3 px-3 py-2 text-sm">
                                            <x-heroicon-m-exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" aria-hidden="true" />
                                            <span class="min-w-0">
                                                <span class="block break-words font-semibold text-gray-900 dark:text-white">{{ $assignment->employee_name }}</span>
                                                <span class="block break-words text-xs text-gray-500 dark:text-gray-400">{{ $assignment->employee_code }} · {{ $staffWarning }} — akan dilepas saat disimpan</span>
                                            </span>
                                        </div>
                                    @else
                                        <label class="flex cursor-pointer items-start gap-3 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <input type="checkbox" wire:model="selectedStaffIds" value="{{ $assignment->employee_id }}" @disabled($staffConfirmation) class="mt-0.5 {{ $checkboxClass }}">
                                            <span class="min-w-0">
                                                <span class="block break-words font-semibold text-gray-900 dark:text-white">{{ $assignment->employee_name }}
                                                    @if($staffWarning)<span class="ml-1 inline-flex items-center rounded bg-amber-100 px-1 align-middle text-[10px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">{{ $staffWarning }}</span>@endif
                                                </span>
                                                <span class="block break-words text-xs text-gray-500 dark:text-gray-400">{{ $assignment->employee_code }}{{ $assignment->employee_position ? ' · '.$assignment->employee_position : '' }}</span>
                                                @if($staffWarning)
                                                    <span class="block text-xs text-amber-700 dark:text-amber-300">Tidak dapat dipilih kembali setelah dilepas.</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </fieldset>
                @endif

                <fieldset>
                    <legend class="mb-1 text-xs font-bold uppercase tracking-wide text-gray-500">Employee aktif {{ $staffOrder->branch->name }}</legend>
                    <div class="relative">
                        <div wire:loading.flex wire:target="staffSearch" class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/70 dark:bg-gray-900/70" role="status">
                            <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-gray-300"><x-filament::loading-indicator class="h-4 w-4" /> Mencari...</span>
                        </div>
                        @if($staffPicker['employees']->isEmpty())
                            <p class="rounded-xl border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">{{ trim($staffSearch) !== '' ? 'Tidak ada employee aktif yang cocok dengan pencarian.' : 'Tidak ada employee aktif lain di Branch ini.' }}</p>
                        @else
                            <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                                @foreach($staffPicker['employees'] as $employee)
                                    <li wire:key="staff-candidate-{{ $employee->id }}">
                                        <label class="flex cursor-pointer items-start gap-3 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <input type="checkbox" wire:model="selectedStaffIds" value="{{ $employee->id }}" @disabled($staffConfirmation) class="mt-0.5 {{ $checkboxClass }}">
                                            <span class="min-w-0">
                                                <span class="block break-words font-semibold text-gray-900 dark:text-white">{{ $employee->name }}</span>
                                                <span class="block break-words text-xs text-gray-500 dark:text-gray-400">{{ $employee->employee_code }} · {{ $employee->position }}</span>
                                            </span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                            @if($staffPicker['hasMore'])
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Menampilkan {{ \App\Services\Kitchen\ProductionOrderDetailBuilder::STAFF_CANDIDATE_LIMIT }} employee pertama. Persempit pencarian untuk melihat yang lain.</p>
                            @endif
                        @endif
                    </div>
                </fieldset>

                @error('selectedStaffIds')
                    <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" role="alert">{{ $message }}</p>
                @enderror

                @if($staffConfirmation)
                    <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100" role="alertdialog" aria-labelledby="kitchen-staff-confirm-title">
                        <p id="kitchen-staff-confirm-title" class="font-semibold">Konfirmasi perubahan Staff Produksi</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5">
                            @foreach($staffConfirmation as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                        <div class="mt-3 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button type="button" wire:click="cancelStaffConfirmation" class="h-9 rounded-lg border border-amber-300 px-3 text-sm font-semibold dark:border-amber-700">Kembali</button>
                            <button type="button" wire:click="saveStaff(true)" wire:loading.attr="disabled" wire:target="saveStaff" class="h-9 rounded-lg bg-amber-600 px-3 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-60">Ya, simpan perubahan</button>
                        </div>
                    </div>
                @endif
            </div>
            <div class="flex flex-col-reverse gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500 dark:text-gray-400" aria-live="polite"><span x-text="$wire.selectedStaffIds.length">{{ count($selectedStaffIds) }}</span> Staff Produksi dipilih</p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row">
                    <button type="button" wire:click="closeStaffPicker" class="h-9 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                    <button type="button" wire:click="saveStaff" wire:loading.attr="disabled" wire:target="saveStaff" @disabled($staffConfirmation) class="h-9 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-indigo-500 dark:hover:bg-indigo-400">Simpan Staff Produksi</button>
                </div>
            </div>
        </x-rnd.picker-modal>
    @endif
</div>
