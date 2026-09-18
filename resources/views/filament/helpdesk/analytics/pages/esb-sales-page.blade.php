<x-filament-panels::page>

<div class="space-y-5">

    {{-- Filter Card --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-end gap-4">

            {{-- Branch selector --}}
            <div class="min-w-[220px] flex-1">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label>
                <select wire:model="selectedBranchId"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <option value="">-- Pilih Branch --</option>
                    @foreach($this->getBranches() as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->activeEsbCodes()->pluck('esb_branch_code')->join(', ') }})</option>
                    @endforeach
                </select>
                @error('selectedBranchId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Date picker --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal</label>
                <input type="date" wire:model="selectedDate"
                       class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                @error('selectedDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Fetch button --}}
            <button wire:click="fetch" wire:loading.attr="disabled"
                    class="flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 active:bg-primary-800 disabled:opacity-60">
                <svg wire:loading wire:target="fetch" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <svg wire:loading.remove wire:target="fetch" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                Fetch dari ESB
            </button>
        </div>

        @if($this->getBranches() === [])
            <p class="mt-3 text-xs text-amber-600 dark:text-amber-400">
                Belum ada branch dengan ESB Branch Code. Silakan set ESB Branch Code di Master &rsaquo; Branch.
            </p>
        @endif
    </div>

    {{-- Results --}}
    @if($fetched)
        @if(count($esbRows) > 0)
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">

                {{-- Header --}}
                <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                Rekapitulasi Pembayaran
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ \Carbon\Carbon::parse($selectedDate)->isoFormat('dddd, D MMMM Y') }}
                                &mdash;
                                {{ collect($this->getBranches())->firstWhere('id', $selectedBranchId)?->name }}
                            </p>
                        </div>
                        <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                            {{ count($esbRows) }} metode
                        </span>
                    </div>
                </div>

                {{-- Table --}}
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                            <th class="px-5 py-3 text-left">Metode Pembayaran</th>
                            <th class="px-5 py-3 text-left">Tipe</th>
                            <th class="px-5 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($esbRows as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-5 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $row['name'] }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        {{ $row['type'] ?: '-' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-100">
                                    Rp {{ number_format($row['total'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                            <td colspan="2" class="px-5 py-3.5 text-sm font-bold text-gray-700 dark:text-gray-200">Grand Total</td>
                            <td class="px-5 py-3.5 text-right font-mono text-base font-bold text-primary-700 dark:text-primary-400">
                                Rp {{ number_format($this->grandTotal(), 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-white py-12 text-center dark:border-gray-700 dark:bg-gray-900">
                <svg class="h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada data penjualan untuk tanggal ini.</p>
            </div>
        @endif
    @endif

</div>

</x-filament-panels::page>
