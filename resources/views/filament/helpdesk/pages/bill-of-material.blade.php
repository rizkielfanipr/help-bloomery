<x-filament-panels::page>
    <div class="space-y-5">
        <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white shadow-lg shadow-blue-500/15">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-sm font-medium text-blue-100">Research &amp; Development</p>
                    <h2 class="mt-1 text-2xl font-bold">Master Bill of Material</h2>
                    <p class="mt-1 text-sm text-blue-100">Data resep Assembly tersinkron langsung dari ESB Core.</p>
                </div>
                @if(auth()->user()?->hasRole('SUPERADMIN') || auth()->user()?->can('create bill of materials'))
                    <a href="{{ \App\Filament\Helpdesk\Pages\CreateBomRecipePage::getUrl() }}"
                       class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50">
                        <x-heroicon-o-plus class="h-5 w-5" />
                        Buat Resep
                    </a>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Nama Produk</label>
                    <input wire:model="productName" wire:keydown.enter="search" type="text" placeholder="Cari produk..."
                           class="w-full rounded-xl border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Satuan</label>
                    <input wire:model="uomName" wire:keydown.enter="search" type="text" placeholder="Contoh: PCS"
                           class="w-full rounded-xl border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Status</label>
                    <select wire:model="status" class="w-full rounded-xl border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                        <option value="1">Aktif</option>
                        <option value="2">Tidak Aktif</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button wire:click="search" wire:loading.attr="disabled"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                        <x-heroicon-o-magnifying-glass class="h-4 w-4" />
                        Cari
                    </button>
                    <button wire:click="resetFilters" title="Reset filter"
                            class="rounded-xl border border-gray-300 p-2.5 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                        <x-heroicon-o-arrow-path class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Daftar Resep</h3>
                    <p class="text-xs text-gray-500">{{ number_format($total) }} data ditemukan</p>
                </div>
                <div wire:loading class="text-sm font-medium text-blue-600">Mengambil data...</div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800/70 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 text-left">Kode / ID</th>
                            <th class="px-5 py-3 text-left">Nama BOM</th>
                            <th class="px-5 py-3 text-left">Produk Hasil</th>
                            <th class="px-5 py-3 text-left">Tipe</th>
                            <th class="px-5 py-3 text-left">Satuan</th>
                            <th class="px-5 py-3 text-center">Status</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($rows as $row)
                            <tr class="transition hover:bg-blue-50/40 dark:hover:bg-blue-950/20">
                                <td class="px-5 py-4">
                                    <p class="font-mono font-semibold text-blue-700 dark:text-blue-400">{{ $row['bomCode'] ?? '-' }}</p>
                                    <p class="text-xs text-gray-400">ID {{ $row['bomID'] ?? '-' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $row['bomName'] ?? '-' }}</p>
                                    @if(!empty($row['notes']))
                                        <p class="mt-0.5 max-w-xs truncate text-xs text-gray-500">{{ $row['notes'] }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-gray-700 dark:text-gray-200">{{ $row['productName'] ?? '-' }}</td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                                        {{ $row['bomTypeName'] ?? 'Assembly' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $row['uomName'] ?? '-' }}</td>
                                <td class="px-5 py-4 text-center">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ (int) ($row['flagActive'] ?? 0) === 1 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">
                                        {{ (int) ($row['flagActive'] ?? 0) === 1 ? 'Aktif' : 'Tidak Aktif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ \App\Filament\Helpdesk\Pages\ViewBomPage::getUrl(['bom' => $row['bomID']]) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">View</a>
                                        @if(auth()->user()?->hasRole('SUPERADMIN') || auth()->user()?->can('edit bill of materials'))
                                            <a href="{{ \App\Filament\Helpdesk\Pages\EditBomRecipePage::getUrl(['bom' => $row['bomID']]) }}" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Update</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            @if($loaded)
                                <tr>
                                    <td colspan="7" class="px-5 py-14 text-center text-gray-500">
                                        <x-heroicon-o-beaker class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                                        Belum ada data BOM yang dapat ditampilkan.
                                    </td>
                                </tr>
                            @endif
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($total > 0)
                <div class="flex items-center justify-between border-t border-gray-200 px-5 py-4 text-sm dark:border-gray-700">
                    <p class="text-gray-500">Halaman {{ $page }} dari {{ max(1, (int) ceil($total / $limit)) }}</p>
                    <div class="flex gap-2">
                        <button wire:click="previousPage" @disabled($page <= 1)
                                class="rounded-lg border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                            Sebelumnya
                        </button>
                        <button wire:click="nextPage" @disabled($page * $limit >= $total)
                                class="rounded-lg border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                            Berikutnya
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
