<x-filament-panels::page>
    @if(! $unlocked)
        <div class="mx-auto flex min-h-[65vh] w-full max-w-lg items-center">
            <section class="w-full rounded-2xl border border-blue-200 bg-white p-7 text-center dark:border-blue-900 dark:bg-gray-900">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                    <x-heroicon-o-lock-closed class="h-8 w-8" />
                </div>
                <h2 class="mt-5 text-2xl font-bold text-gray-900 dark:text-white">Resep Dilindungi PIN</h2>
                <p class="mt-2 text-sm leading-6 text-gray-500">Masukkan PIN keamanan untuk membuka detail Bill of Material {{ $productName }}. Akses berlaku sementara pada perangkat ini.</p>
                <form wire:submit="verifyPin" class="mt-6">
                    <input wire:model="pin" type="password" inputmode="numeric" autocomplete="one-time-code" autofocus placeholder="Masukkan PIN" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-center text-lg font-bold tracking-[0.35em] focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    @error('pin')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                    <button type="submit" wire:loading.attr="disabled" wire:target="verifyPin" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                        <x-heroicon-o-lock-open class="h-4 w-4" />
                        <span wire:loading.remove wire:target="verifyPin">Buka Resep</span>
                        <span wire:loading wire:target="verifyPin">Memverifikasi...</span>
                    </button>
                </form>
                <a href="{{ \App\Filament\Helpdesk\Resources\Projects\ProjectResource::getUrl('view', ['record' => $projectId]) }}" class="mt-4 inline-block text-sm font-semibold text-gray-500 hover:text-blue-600">Kembali ke Project</a>
            </section>
        </div>
    @else
    @php($bom = $detail)
    <div class="w-full space-y-4">
        <div class="rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-6 dark:border-blue-900/50 dark:from-blue-950/40 dark:to-indigo-950/40">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <p class="mb-2 text-sm font-semibold text-blue-700 dark:text-blue-300">{{ $projectName }} · {{ $productName }}</p>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="rounded-md bg-blue-600 px-2.5 py-1 font-mono text-xs font-bold text-white">{{ $bom['bomCode'] ?: 'Tanpa kode' }}</span>
                        <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">{{ $bom['bomTypeName'] ?? 'Assembly' }}</span>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $bom['bomName'] }}</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $bom['productName'] ?: '-' }} · {{ $bom['uomName'] ?: '-' }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ \App\Filament\Helpdesk\Pages\ViewProjectProductPage::getUrl(['project' => $projectId, 'product' => $productId]) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200">Kembali</a>
                    @if(auth()->user()?->hasRole('SUPERADMIN') || auth()->user()?->can('edit bill of materials'))
                        <a href="{{ \App\Filament\Helpdesk\Pages\EditBomRecipePage::getUrl(['project' => $projectId, 'product' => $productId, 'bom' => $bomId]) }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Update BOM</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[2fr_1fr]">
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-4 font-bold text-gray-900 dark:text-white">Informasi BOM</h3>
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach([
                        'Product Name' => $bom['productName'] ?? '-',
                        'Product Code' => $bom['productCode'] ?? '-',
                        'Unit' => $bom['uomName'] ?? '-',
                        'Total Biaya' => 'Rp '.number_format((float) ($bom['bomCostTotal'] ?? 0), 0, ',', '.'),
                        'Dibuat Oleh' => $bom['createdBy'] ?? '-',
                        'Diubah Oleh' => $bom['editedBy'] ?? '-',
                    ] as $label => $value)
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</dt>
                            <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $value ?: '-' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-3 font-bold text-gray-900 dark:text-white">Catatan</h3>
                <p class="whitespace-pre-wrap text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $bom['notes'] ?: 'Tidak ada catatan.' }}</p>
            </section>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <h3 class="font-bold text-gray-900 dark:text-white">Bahan Penyusun</h3>
                <p class="text-xs text-gray-500">{{ count($bom['bomDetails'] ?? []) }} bahan</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left">Product Name</th>
                            <th class="px-4 py-3 text-left">Product Code</th>
                            <th class="px-4 py-3 text-left">Unit</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Last HPP</th>
                            <th class="px-4 py-3 text-right">Waste</th>
                            <th class="px-4 py-3 text-left">Print Group</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($bom['bomDetails'] ?? [] as $item)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $item['productName'] }}</td>
                                <td class="px-4 py-3 font-mono text-blue-700 dark:text-blue-300">{{ $item['productCode'] ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $item['uomName'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $item['qty'], 4, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($item['lastHpp'] ?? 0), 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($item['yieldPercent'] ?? 0), 2, ',', '.') }}%</td>
                                <td class="px-4 py-3">{{ $item['printGroup'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12 text-center text-gray-500">Belum ada bahan penyusun.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    @endif
</x-filament-panels::page>
