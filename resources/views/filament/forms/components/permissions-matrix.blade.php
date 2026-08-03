@php
    $config     = app(\App\Services\PermissionRegistry::class)->groups();
    $options    = $getOptions();              // [id => name]
    $nameToId   = array_flip($options);       // [name => id]
    $statePath  = $getStatePath();
    $actionLabels = [
        'access' => 'Akses',
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ];
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
    class="space-y-5"
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
    <div class="flex min-h-12 items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-2.5 dark:border-white/10 dark:bg-white/5">
        <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pilih Semua Permission</span>
        <button
            type="button"
            @click="toggleAll({{ json_encode($allIds) }})"
            :title="isAllChecked({{ json_encode($allIds) }}) ? 'Hapus semua permission' : 'Pilih semua permission'"
            :aria-label="isAllChecked({{ json_encode($allIds) }}) ? 'Hapus semua permission' : 'Pilih semua permission'"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition"
            :class="isAllChecked({{ json_encode($allIds) }})
                ? 'bg-danger-100 text-danger-700 hover:bg-danger-200 dark:bg-danger-500/20 dark:text-danger-400'
                : 'bg-primary-100 text-primary-700 hover:bg-primary-200 dark:bg-primary-500/20 dark:text-primary-400'"
        >
            <svg x-show="!isAllChecked({{ json_encode($allIds) }})" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <svg x-show="isAllChecked({{ json_encode($allIds) }})" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </button>
    </div>

    @foreach ($config as $groupName => $resources)
        @php
            $groupIds = [];
            foreach ($resources as $perms) {
                foreach ($perms as $perm) {
                    if (isset($nameToId[$perm])) $groupIds[] = $nameToId[$perm];
                }
            }
        @endphp

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">

            {{-- Group Header --}}
            <div class="flex min-h-12 items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-2.5 dark:border-white/10 dark:bg-white/[0.07]">
                <span class="text-xs font-bold uppercase tracking-widest text-gray-600 dark:text-gray-300">
                    {{ $groupName }}
                </span>
                <button
                    type="button"
                    @click="toggleGroup({{ json_encode($groupIds) }})"
                    :title="isGroupAllChecked({{ json_encode($groupIds) }}) ? 'Hapus semua permission grup ini' : 'Pilih semua permission grup ini'"
                    :aria-label="isGroupAllChecked({{ json_encode($groupIds) }}) ? 'Hapus semua permission grup ini' : 'Pilih semua permission grup ini'"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition"
                    :class="isGroupAllChecked({{ json_encode($groupIds) }})
                        ? 'bg-danger-100 text-danger-600 hover:bg-danger-200 dark:bg-danger-500/20 dark:text-danger-400'
                        : 'bg-primary-100 text-primary-600 hover:bg-primary-200 dark:bg-primary-500/20 dark:text-primary-400'"
                >
                    <svg x-show="!isGroupAllChecked({{ json_encode($groupIds) }})" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <svg x-show="isGroupAllChecked({{ json_encode($groupIds) }})" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </button>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] table-fixed text-sm">
                <colgroup>
                    <col class="w-[40%]">
                    @foreach ($actionLabels as $action => $label)
                        <col class="w-[12%]">
                    @endforeach
                </colgroup>
                <thead>
                    <tr class="h-11 border-b border-gray-100 bg-white dark:border-white/5 dark:bg-gray-900">
                        <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Resource</th>
                        @foreach ($actionLabels as $action => $label)
                            <th class="px-2 py-2 text-center text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-white/[0.03]">
                    @foreach ($resources as $resourceName => $permissions)
                        @php
                            $permByAction = [];
                            foreach ($permissions as $perm) {
                                if (!isset($nameToId[$perm])) continue;
                                foreach (array_keys($actionLabels) as $action) {
                                    if (str_starts_with($perm, $action . ' ')) {
                                        $permByAction[$action] = $nameToId[$perm];
                                    }
                                }
                            }
                        @endphp
                        <tr class="h-14 hover:bg-gray-50/60 dark:hover:bg-white/[0.02]">
                            <td class="px-4 py-3 align-middle font-medium text-gray-700 dark:text-gray-200">
                                {{ $resourceName }}
                            </td>
                            @foreach ($actionLabels as $action => $label)
                                <td class="px-2 py-3 text-center align-middle">
                                    @if (isset($permByAction[$action]))
                                        @php $permId = $permByAction[$action]; @endphp
                                        <label class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-white/5">
                                            <input
                                                type="checkbox"
                                                value="{{ $permId }}"
                                                :checked="isChecked({{ $permId }})"
                                                @change="toggle({{ $permId }})"
                                                class="h-4 w-4 rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                        </label>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endforeach
</div>
