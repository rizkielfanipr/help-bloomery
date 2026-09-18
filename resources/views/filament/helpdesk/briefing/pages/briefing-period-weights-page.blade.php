<x-filament-panels::page>
    <x-filament::section>
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            Bobot ini menentukan seberapa besar kontribusi tiap periode (Harian/Mingguan/Bulanan) terhadap total nilai briefing bulanan. Total tiap baris harus 100%. Baris cabang boleh dikosongkan seluruhnya agar cabang tersebut memakai bobot <strong>Default</strong>.
        </p>

        <form wire:submit="save">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="py-2 pr-4">Cabang</th>
                            <th class="px-2 py-2 text-center">Bobot Harian (%)</th>
                            <th class="px-2 py-2 text-center">Bobot Mingguan (%)</th>
                            <th class="px-2 py-2 text-center">Bobot Bulanan (%)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($rows as $index => $row)
                            <tr wire:key="row-{{ $row['branch_id'] ?? 'default' }}">
                                <td class="py-2.5 pr-4 font-medium text-gray-800 dark:text-gray-200">
                                    {{ $row['branch_label'] }}
                                </td>
                                <td class="px-2 py-2.5">
                                    <input type="number" step="0.01" min="0" max="100"
                                           wire:model="rows.{{ $index }}.daily_weight"
                                           placeholder="{{ $row['branch_id'] === null ? '' : 'default' }}"
                                           class="w-24 rounded-lg border border-gray-300 text-center text-sm focus:border-primary-400 focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    @error("rows.{$index}.daily_weight") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-2.5">
                                    <input type="number" step="0.01" min="0" max="100"
                                           wire:model="rows.{{ $index }}.weekly_weight"
                                           placeholder="{{ $row['branch_id'] === null ? '' : 'default' }}"
                                           class="w-24 rounded-lg border border-gray-300 text-center text-sm focus:border-primary-400 focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    @error("rows.{$index}.weekly_weight") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-2.5">
                                    <input type="number" step="0.01" min="0" max="100"
                                           wire:model="rows.{{ $index }}.monthly_weight"
                                           placeholder="{{ $row['branch_id'] === null ? '' : 'default' }}"
                                           class="w-24 rounded-lg border border-gray-300 text-center text-sm focus:border-primary-400 focus:ring-0 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    @error("rows.{$index}.monthly_weight") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit" icon="heroicon-m-check">
                    Simpan Bobot
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
