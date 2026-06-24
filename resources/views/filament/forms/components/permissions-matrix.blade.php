@php
    $config     = config('permissions', []);
    $options    = $getOptions();              // [id => name]
    $nameToId   = array_flip($options);       // [name => id]
    $statePath  = $getStatePath();
@endphp

<div
    x-data="{
        state: $wire.entangle('{{ $statePath }}').live,

        isChecked(id) {
            return Array.isArray(this.state) && this.state.map(Number).includes(Number(id));
        },

        toggle(id) {
            id = Number(id);
            if (!Array.isArray(this.state)) this.state = [];
            const mapped = this.state.map(Number);
            const idx    = mapped.indexOf(id);
            if (idx === -1) {
                this.state = [...this.state, id];
            } else {
                this.state = this.state.filter(v => Number(v) !== id);
            }
        },

        isGroupAllChecked(ids) {
            if (!Array.isArray(this.state) || !ids.length) return false;
            const mapped = this.state.map(Number);
            return ids.every(id => mapped.includes(Number(id)));
        },

        toggleGroup(ids, forceOn) {
            if (!Array.isArray(this.state)) this.state = [];
            const mapped  = this.state.map(Number);
            const checked = forceOn !== undefined ? forceOn : !this.isGroupAllChecked(ids);
            if (checked) {
                const merged = [...new Set([...mapped, ...ids.map(Number)])];
                this.state   = merged;
            } else {
                this.state = mapped.filter(v => !ids.map(Number).includes(Number(v)));
            }
        },

        isAllChecked(allIds) {
            if (!Array.isArray(this.state) || !allIds.length) return false;
            const mapped = this.state.map(Number);
            return allIds.every(id => mapped.includes(Number(id)));
        },

        toggleAll(allIds) {
            const checked = !this.isAllChecked(allIds);
            if (checked) {
                this.state = [...new Set(allIds.map(Number))];
            } else {
                this.state = [];
            }
        },
    }"
    class="space-y-4"
>
    @php
        $allIds = [];
        foreach ($config as $groupResources) {
            foreach ($groupResources as $perms) {
                foreach ($perms as $perm) {
                    if (isset($nameToId[$perm])) $allIds[] = $nameToId[$perm];
                }
            }
        }
    @endphp

    {{-- Select All --}}
    <div class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-2.5 dark:bg-white/5">
        <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pilih Semua Permission</span>
        <button
            type="button"
            @click="toggleAll({{ json_encode($allIds) }})"
            class="rounded-lg px-3 py-1 text-xs font-medium transition"
            :class="isAllChecked({{ json_encode($allIds) }})
                ? 'bg-danger-100 text-danger-700 hover:bg-danger-200 dark:bg-danger-500/20 dark:text-danger-400'
                : 'bg-primary-100 text-primary-700 hover:bg-primary-200 dark:bg-primary-500/20 dark:text-primary-400'"
            x-text="isAllChecked({{ json_encode($allIds) }}) ? 'Hapus Semua' : 'Pilih Semua'"
        ></button>
    </div>

    @foreach ($config as $groupName => $resources)
        @php
            $groupIds = [];
            foreach ($resources as $perms) {
                foreach ($perms as $perm) {
                    if (isset($nameToId[$perm])) $groupIds[] = $nameToId[$perm];
                }
            }

            $actionLabels = ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'];
        @endphp

        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">

            {{-- Group Header --}}
            <div class="flex items-center justify-between bg-gray-100 px-4 py-2.5 dark:bg-white/[0.07]">
                <span class="text-xs font-bold uppercase tracking-widest text-gray-600 dark:text-gray-300">
                    {{ $groupName }}
                </span>
                <button
                    type="button"
                    @click="toggleGroup({{ json_encode($groupIds) }})"
                    class="rounded px-2.5 py-0.5 text-[11px] font-semibold transition"
                    :class="isGroupAllChecked({{ json_encode($groupIds) }})
                        ? 'bg-danger-100 text-danger-600 hover:bg-danger-200 dark:bg-danger-500/20 dark:text-danger-400'
                        : 'bg-primary-100 text-primary-600 hover:bg-primary-200 dark:bg-primary-500/20 dark:text-primary-400'"
                    x-text="isGroupAllChecked({{ json_encode($groupIds) }}) ? 'Hapus Grup' : 'Pilih Grup'"
                ></button>
            </div>

            {{-- Table --}}
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Resource</th>
                        @foreach ($actionLabels as $action => $label)
                            <th class="px-3 py-2 text-center text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $label }}</th>
                        @endforeach
                        <th class="px-3 py-2 text-center text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Semua</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-white/[0.03]">
                    @foreach ($resources as $resourceName => $permissions)
                        @php
                            $resourceIds = [];
                            $permByAction = [];
                            foreach ($permissions as $perm) {
                                if (!isset($nameToId[$perm])) continue;
                                $resourceIds[] = $nameToId[$perm];
                                foreach (array_keys($actionLabels) as $action) {
                                    if (str_starts_with($perm, $action . ' ')) {
                                        $permByAction[$action] = $nameToId[$perm];
                                    }
                                }
                            }
                        @endphp
                        <tr class="hover:bg-gray-50/60 dark:hover:bg-white/[0.02]">
                            <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200">
                                {{ $resourceName }}
                            </td>
                            @foreach ($actionLabels as $action => $label)
                                <td class="px-3 py-3 text-center">
                                    @if (isset($permByAction[$action]))
                                        @php $permId = $permByAction[$action]; @endphp
                                        <label class="inline-flex cursor-pointer items-center justify-center">
                                            <input
                                                type="checkbox"
                                                value="{{ $permId }}"
                                                :checked="isChecked({{ $permId }})"
                                                @change="toggle({{ $permId }})"
                                                class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                        </label>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>
                            @endforeach
                            {{-- Row toggle --}}
                            <td class="px-3 py-3 text-center">
                                @if (count($resourceIds) > 0)
                                    <button
                                        type="button"
                                        @click="toggleGroup({{ json_encode($resourceIds) }})"
                                        class="rounded px-2 py-0.5 text-[10px] font-medium transition"
                                        :class="isGroupAllChecked({{ json_encode($resourceIds) }})
                                            ? 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400'
                                            : 'bg-primary-50 text-primary-600 hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-400'"
                                        x-text="isGroupAllChecked({{ json_encode($resourceIds) }}) ? 'Hapus' : 'Semua'"
                                    ></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
