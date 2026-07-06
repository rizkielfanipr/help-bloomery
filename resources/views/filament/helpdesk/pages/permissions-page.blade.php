<x-filament-panels::page>
    @php
        $permissions = $this->getPermissions();

        $moduleIcons = [
            'Human Resources'       => ['icon' => 'heroicon-o-users', 'color' => 'violet'],
            'Driver'                => ['icon' => 'heroicon-o-truck', 'color' => 'blue'],
            'Teknisi'               => ['icon' => 'heroicon-o-wrench-screwdriver', 'color' => 'orange'],
            'Finance'               => ['icon' => 'heroicon-o-banknotes', 'color' => 'green'],
            'Purchasing'            => ['icon' => 'heroicon-o-shopping-cart', 'color' => 'teal'],
            'Brand Marketing'       => ['icon' => 'heroicon-o-megaphone', 'color' => 'pink'],
            'Information Technology'=> ['icon' => 'heroicon-o-computer-desktop', 'color' => 'cyan'],
            'Management Access'     => ['icon' => 'heroicon-o-shield-check', 'color' => 'red'],
            'Master'                => ['icon' => 'heroicon-o-circle-stack', 'color' => 'gray'],
        ];

        $colorMap = [
            'violet' => ['header' => 'bg-violet-50 dark:bg-violet-950/30 border-violet-200 dark:border-violet-800', 'icon' => 'text-violet-600 dark:text-violet-400', 'title' => 'text-violet-900 dark:text-violet-100'],
            'blue'   => ['header' => 'bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-800',   'icon' => 'text-blue-600 dark:text-blue-400',   'title' => 'text-blue-900 dark:text-blue-100'],
            'orange' => ['header' => 'bg-orange-50 dark:bg-orange-950/30 border-orange-200 dark:border-orange-800', 'icon' => 'text-orange-600 dark:text-orange-400', 'title' => 'text-orange-900 dark:text-orange-100'],
            'green'  => ['header' => 'bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-800', 'icon' => 'text-green-600 dark:text-green-400', 'title' => 'text-green-900 dark:text-green-100'],
            'teal'   => ['header' => 'bg-teal-50 dark:bg-teal-950/30 border-teal-200 dark:border-teal-800',  'icon' => 'text-teal-600 dark:text-teal-400',  'title' => 'text-teal-900 dark:text-teal-100'],
            'pink'   => ['header' => 'bg-pink-50 dark:bg-pink-950/30 border-pink-200 dark:border-pink-800',  'icon' => 'text-pink-600 dark:text-pink-400',  'title' => 'text-pink-900 dark:text-pink-100'],
            'cyan'   => ['header' => 'bg-cyan-50 dark:bg-cyan-950/30 border-cyan-200 dark:border-cyan-800',  'icon' => 'text-cyan-600 dark:text-cyan-400',  'title' => 'text-cyan-900 dark:text-cyan-100'],
            'red'    => ['header' => 'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-800',     'icon' => 'text-red-600 dark:text-red-400',     'title' => 'text-red-900 dark:text-red-100'],
            'gray'   => ['header' => 'bg-gray-50 dark:bg-gray-900/30 border-gray-200 dark:border-gray-700', 'icon' => 'text-gray-600 dark:text-gray-400',   'title' => 'text-gray-900 dark:text-gray-100'],
        ];

        $actionBadge = [
            'view'   => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 ring-1 ring-blue-200 dark:ring-blue-700',
            'create' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300 ring-1 ring-green-200 dark:ring-green-700',
            'edit'   => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300 ring-1 ring-yellow-200 dark:ring-yellow-700',
            'delete' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300 ring-1 ring-red-200 dark:ring-red-700',
        ];
    @endphp

    {{-- Page header description --}}
    <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/50">
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Halaman ini menampilkan seluruh permission yang tersedia di sistem, dikelompokkan per modul.
            Permission ini dapat diberikan kepada <strong class="font-semibold text-slate-800 dark:text-slate-200">Role</strong> melalui halaman Role &amp; Permission.
        </p>
        <div class="mt-3 flex flex-wrap gap-3">
            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-blue-400"></span> View
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-green-400"></span> Create
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-yellow-400"></span> Edit
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-red-400"></span> Delete
            </span>
        </div>
    </div>

    {{-- Module cards grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach ($permissions as $moduleName => $resources)
            @php
                $meta   = $moduleIcons[$moduleName] ?? ['icon' => 'heroicon-o-squares-2x2', 'color' => 'gray'];
                $colors = $colorMap[$meta['color']];
            @endphp

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                {{-- Module header --}}
                <div class="flex items-center gap-3 border-b px-5 py-3.5 {{ $colors['header'] }}">
                    <x-filament::icon
                        :icon="$meta['icon']"
                        class="h-5 w-5 shrink-0 {{ $colors['icon'] }}"
                    />
                    <h3 class="text-sm font-semibold tracking-wide {{ $colors['title'] }}">
                        {{ $moduleName }}
                    </h3>
                    <span class="ml-auto text-xs font-medium text-slate-400 dark:text-slate-500">
                        {{ count($resources) }} resource
                    </span>
                </div>

                {{-- Resource rows --}}
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($resources as $resourceName => $perms)
                        <div class="flex flex-wrap items-center gap-2 px-5 py-3">
                            <span class="min-w-[140px] text-xs font-medium text-slate-600 dark:text-slate-300">
                                {{ $resourceName }}
                            </span>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($perms as $perm)
                                    @php
                                        $action = explode(' ', $perm)[0];
                                        $badgeClass = $actionBadge[$action] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
                                    @endphp
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-mono font-medium {{ $badgeClass }}">
                                        {{ $perm }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
