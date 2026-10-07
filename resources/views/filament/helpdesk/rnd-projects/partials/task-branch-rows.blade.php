{{-- Shared Branch & PIC picker for Tambah Task, Copy Task, and Gunakan Template. Expects $rowsProperty and $rows. --}}
<div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
    <div class="mb-3 flex items-center justify-between gap-2">
        <p class="text-sm font-bold text-gray-800 dark:text-gray-100">Branch &amp; PIC *</p>
        <button type="button" wire:click="addBranchRow('{{ $rowsProperty }}')" class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">
            <x-heroicon-o-plus class="h-3.5 w-3.5" /> Tambah Branch
        </button>
    </div>
    @error($rowsProperty)<p class="mb-2 text-xs text-red-600">{{ $message }}</p>@enderror
    <div class="space-y-3">
        @foreach($rows as $index => $row)
            <div wire:key="{{ $rowsProperty }}-row-{{ $index }}" class="grid gap-2 rounded-lg border border-gray-100 bg-gray-50/60 p-3 sm:grid-cols-[1fr_1fr_auto] dark:border-gray-800 dark:bg-gray-800/40">
                <div>
                    <label for="{{ $rowsProperty }}-{{ $index }}-branch" class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Branch</label>
                    <select id="{{ $rowsProperty }}-{{ $index }}-branch" wire:model.live="{{ $rowsProperty }}.{{ $index }}.branch_id" class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        <option value="">Pilih Branch...</option>
                        @foreach($this->taskFilterBranches() as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    @error("{$rowsProperty}.{$index}.branch_id")<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                </div>
                <fieldset>
                    <legend class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">PIC</legend>
                    @php $eligiblePics = $this->eligiblePicsForBranch($row['branch_id']); @endphp
                    @if(blank($row['branch_id']))
                        <p class="rounded-lg border border-dashed border-gray-300 px-2.5 py-2 text-xs text-gray-400 dark:border-gray-600">Pilih Branch terlebih dahulu.</p>
                    @elseif($eligiblePics === [])
                        <p class="rounded-lg border border-dashed border-amber-300 px-2.5 py-2 text-xs text-amber-700 dark:border-amber-800 dark:text-amber-300">Belum ada pengguna aktif dengan akses ke Branch ini.</p>
                    @else
                        <div class="max-h-32 space-y-1 overflow-y-auto rounded-lg border border-gray-300 bg-white p-2 dark:border-gray-600 dark:bg-gray-900">
                            @foreach($eligiblePics as $userId => $label)
                                <label wire:key="{{ $rowsProperty }}-{{ $index }}-pic-{{ $userId }}" class="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800">
                                    <input type="checkbox" value="{{ $userId }}" wire:model="{{ $rowsProperty }}.{{ $index }}.user_ids" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="truncate">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error("{$rowsProperty}.{$index}.user_ids")<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                </fieldset>
                <div class="flex items-end">
                    @if(count($rows) > 1)
                        <button type="button" wire:click="removeBranchRow('{{ $rowsProperty }}', {{ $index }})" aria-label="Hapus Branch ini" class="rounded-lg border border-red-200 p-2 text-red-600 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950/30">
                            <x-heroicon-o-trash class="h-4 w-4" />
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
