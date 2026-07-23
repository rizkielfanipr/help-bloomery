<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="helpdeskLayout"
    x-init="init()"
    :class="dark ? 'dark' : ''"
    class="h-full"
>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) — Helpdesk</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/filament/helpdesk/theme.css', 'resources/js/app.js'])

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        html, body { font-family: 'Inter Variable', 'Inter', ui-sans-serif, system-ui, sans-serif; }
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        [x-cloak] { display: none !important; }
    </style>
</head>

@php
    $user     = auth()->user();
    $initials = $user
        ? collect(explode(' ', $user->name))->map(fn ($w) => strtoupper($w[0] ?? ''))->take(2)->join('')
        : 'HD';

    $r = fn (string $name, array $params = []) => app('router')->has($name) ? route($name, $params) : '#';

    $path = request()->path();

    $initialOpen = [];
    if (str_contains($path, 'casual')) { $initialOpen[] = 'casual_staff'; }
    if (str_contains($path, 'briefing-items') || str_contains($path, 'briefing-calendar') || str_contains($path, 'briefing-tasks')) { $initialOpen[] = 'daily_briefing'; }
    if (preg_match('/trip|vehicle|driver/', $path))                  { $initialOpen[] = 'driver'; }
    if (preg_match('/service-request|technician-settings/', $path))  { $initialOpen[] = 'technician'; }
    if (preg_match('/\busers?\b|\broles?\b/', $path))                { $initialOpen[] = 'management'; }
    if (str_contains($path, 'branches'))                              { $initialOpen[] = 'master'; }
    if (str_contains($path, 'sales-report') || str_contains($path, 'payment-method')) { $initialOpen[] = 'finance'; }
    if (str_contains($path, 'design-request') || str_contains($path, 'design-categor')) { $initialOpen[] = 'brand-marketing'; }
    if (str_contains($path, 'erp-repair-request') || str_contains($path, 'erp-module')) { $initialOpen[] = 'it'; }
    if (str_contains($path, 'purchase-request')) { $initialOpen[] = 'purchasing'; }
    $initialOpen = array_slice(array_values(array_unique($initialOpen)), 0, 1);

    $allNavGroups = [
        [
            'id'    => 'casual_staff',
            'label' => 'Casual Staff',
            'icon'  => 'users',
            'items' => [
                ['label' => 'Casual Staff',    'icon' => 'users',     'perm' => 'view casual staff',     'href' => $r('filament.helpdesk.resources.casual-staff.index'),             'active' => request()->is('helpdesk/casual-staff*')],
                ['label' => 'Posisi Casual',   'icon' => 'briefcase', 'perm' => 'view casual positions', 'href' => $r('filament.helpdesk.resources.casual-positions.index'),          'active' => request()->is('helpdesk/casual-positions*')],
                ['label' => 'Lowongan Posisi', 'icon' => 'megaphone', 'perm' => 'view casual openings',  'href' => $r('filament.helpdesk.resources.casual-position-openings.index'), 'active' => request()->is('helpdesk/casual-position-openings*')],
                ['label' => 'Absensi Casual',  'icon' => 'clock',     'perm' => 'view clock records',    'href' => $r('filament.helpdesk.resources.casual-clock-records.index'),      'active' => request()->is('helpdesk/casual-clock-records*')],
            ],
        ],
        [
            'id'    => 'daily_briefing',
            'label' => 'Daily Briefing',
            'icon'  => 'clipboard-list',
            'items' => [
                ['label' => 'Monitoring Poin',   'icon' => 'clipboard-check', 'perm' => 'view briefing items',   'href' => $r('filament.helpdesk.resources.briefing-items.index'),   'active' => request()->is('helpdesk/briefing-items*')],
                ['label' => 'Kalender Briefing', 'icon' => 'calendar-days',   'perm' => 'view briefing records', 'href' => $r('filament.helpdesk.pages.briefing-calendar-page'),      'active' => request()->is('helpdesk/briefing-calendar-page*')],
                ['label' => 'Kelola Poin',       'icon' => 'list-checks',     'perm' => 'view briefing records', 'href' => $r('filament.helpdesk.resources.briefing-tasks.index'),   'active' => request()->is('helpdesk/briefing-tasks*')],
            ],
        ],
        [
            'id'    => 'driver',
            'label' => 'Driver',
            'icon'  => 'truck',
            'items' => [
                ['label' => 'Perjalanan',      'icon' => 'map',      'perm' => 'view trips',       'href' => $r('filament.helpdesk.resources.trips.index'),       'active' => request()->is('helpdesk/trips*')],
                ['label' => 'Rute Perjalanan', 'icon' => 'route',    'perm' => 'view trip routes', 'href' => $r('filament.helpdesk.resources.trip-routes.index'), 'active' => request()->is('helpdesk/trip-routes*')],
                ['label' => 'Kendaraan',       'icon' => 'car',      'perm' => 'view vehicles',    'href' => $r('filament.helpdesk.resources.vehicles.index'),    'active' => request()->is('helpdesk/vehicles*')],
                ['label' => 'Pengaturan', 'icon' => 'settings', 'href' => $r('filament.helpdesk.pages.driver-trip-settings-page'), 'active' => request()->is('driver-trip-settings-page')],
            ],
        ],
        [
            'id'    => 'technician',
            'label' => 'Technician',
            'icon'  => 'wrench',
            'items' => [
                ['label' => 'Permintaan Service', 'icon' => 'clipboard-list', 'perm' => 'view service requests', 'href' => $r('filament.helpdesk.resources.service-requests.index'), 'active' => request()->is('helpdesk/service-requests*')],
                ['label' => 'Pengaturan', 'icon' => 'settings',       'perm' => 'edit service requests', 'href' => $r('filament.helpdesk.pages.technician-settings'),         'active' => request()->is('helpdesk/technician-settings*')],
            ],
        ],
        [
            'id'    => 'it',
            'label' => 'Information Technology',
            'icon'  => 'monitor',
            'items' => [
                ['label' => 'Permintaan ERP', 'icon' => 'server',      'perm' => 'view erp requests', 'href' => $r('filament.helpdesk.resources.erp-repair-requests.index'), 'active' => request()->is('helpdesk/erp-repair-requests*')],
                ['label' => 'Modul ERP',      'icon' => 'layout-grid', 'perm' => 'view erp modules',  'href' => $r('filament.helpdesk.resources.erp-modules.index'),         'active' => request()->is('helpdesk/erp-modules*')],
                ['label' => 'Request Types',  'icon' => 'tags',        'perm' => 'view it request types', 'href' => $r('filament.helpdesk.resources.it-request-types.index'),  'active' => request()->is('helpdesk/it-request-types*')],
            ],
        ],
        [
            'id'    => 'purchasing',
            'label' => 'Purchasing',
            'icon'  => 'shopping-cart',
            'items' => [
                ['label' => 'Permintaan Pembelian', 'icon' => 'shopping-bag', 'perm' => 'view purchase requests', 'href' => $r('filament.helpdesk.resources.purchase-requests.index'), 'active' => request()->is('helpdesk/purchase-requests*')],
            ],
        ],
        [
            'id'    => 'finance',
            'label' => 'Finance',
            'icon'  => 'banknote',
            'items' => [
                ['label' => 'Sales Report',      'icon' => 'bar-chart-2', 'perm' => 'view sales reports',   'href' => $r('filament.helpdesk.resources.sales-reports.index'),   'active' => request()->is('helpdesk/sales-reports*')],
                ['label' => 'Metode Pembayaran', 'icon' => 'credit-card', 'perm' => 'view payment methods', 'href' => $r('filament.helpdesk.resources.payment-methods.index'), 'active' => request()->is('helpdesk/payment-methods*')],
            ],
        ],
        [
            'id'    => 'brand-marketing',
            'label' => 'Brand Marketing',
            'icon'  => 'megaphone',
            'items' => [
                ['label' => 'Permintaan Design', 'icon' => 'palette', 'perm' => 'view design requests',   'href' => $r('filament.helpdesk.resources.design-requests.index'),   'active' => request()->is('helpdesk/design-requests*')],
                ['label' => 'Kategori Design',   'icon' => 'tag',     'perm' => 'view design categories', 'href' => $r('filament.helpdesk.resources.design-categories.index'), 'active' => request()->is('helpdesk/design-categories*')],
            ],
        ],
        [
            'id'    => 'management',
            'label' => 'Management Access',
            'icon'  => 'shield',
            'items' => [
                ['label' => 'Pengguna',          'icon' => 'user', 'perm' => 'view users', 'href' => $r('filament.helpdesk.resources.users.index'), 'active' => request()->is('helpdesk/users*')],
                ['label' => 'Role & Permission', 'icon' => 'lock', 'perm' => 'view roles', 'href' => $r('filament.helpdesk.resources.roles.index'), 'active' => request()->is('helpdesk/roles*')],
            ],
        ],
        [
            'id'    => 'master',
            'label' => 'Master',
            'icon'  => 'database',
            'items' => [
                ['label' => 'Branch', 'icon' => 'building-2', 'perm' => 'view branches', 'href' => $r('filament.helpdesk.resources.branches.index'), 'active' => request()->is('helpdesk/branches*')],
            ],
        ],
    ];

    $navGroups = array_values(array_filter(array_map(function (array $group) use ($user): ?array {
        $items = array_values(array_filter($group['items'], fn ($item) =>
            ! isset($item['perm']) || $user?->can($item['perm'])
        ));
        if (empty($items)) {
            return null;
        }
        $group['items'] = $items;
        return $group;
    }, $allNavGroups)));

    $navSearchGroups = [];
    foreach ($navGroups as $group) {
        $navSearchGroups[] = [
            'id' => $group['id'],
            'label' => $group['label'],
            'items' => array_column($group['items'], 'label'),
        ];
    }
@endphp

<body class="antialiased h-full">
<div class="flex h-screen overflow-hidden bg-[#F8FAFC] dark:bg-[#020617] transition-colors duration-300">

{{-- ═══════════════════════ SIDEBAR ═══════════════════════ --}}
<aside
    class="overflow-hidden transition-all duration-300 ease-in-out shrink-0 fixed inset-y-0 left-0 z-50 w-[280px] lg:relative lg:inset-auto lg:z-auto"
    :class="sidebarOpen ? 'translate-x-0 lg:w-[280px]' : '-translate-x-full lg:w-16 lg:translate-x-0'"
>
<div class="flex h-full w-full flex-col border-r border-slate-200 bg-white dark:border-white/5 dark:bg-[#0F172A]">

    {{-- Logo --}}
    <div class="flex h-20 shrink-0 items-center justify-center border-b border-slate-100 px-3 dark:border-white/5">
        <img
            src="{{ asset('images/bloomery-icon.png') }}"
            alt="Bloomery Patisserie"
            class="h-10 w-10 object-contain dark:brightness-200 dark:opacity-80"
        >
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-0.5">

        {{-- Dashboard --}}
        <a
            x-show="!hasSearch()"
            href="{{ url('helpdesk') }}"
            class="flex items-center rounded-xl py-2 text-[13px] font-medium transition-all duration-300
                {{ request()->is('helpdesk')
                    ? 'bg-blue-50 font-semibold text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                    : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-white/5' }}"
            :class="sidebarOpen ? 'gap-3 px-3' : 'justify-center px-2'"
            :title="!sidebarOpen ? 'Dashboard' : ''"
        >
            <i data-lucide="layout-dashboard" class="h-4 w-4 shrink-0"></i>
            <span
                x-show="sidebarOpen"
                x-transition:enter="transition-opacity ease-out duration-150 delay-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-75"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
            >Dashboard</span>
        </a>

        {{-- Nav Groups --}}
        @foreach ($navGroups as $group)
            <div x-show="groupMatches('{{ $group['id'] }}')" x-cloak>
                <button
                    @click="toggleSidebarGroup('{{ $group['id'] }}')"
                    class="flex w-full items-center rounded-xl py-2 text-[13px] font-medium text-slate-600 transition-all duration-300 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-white/5"
                    :class="sidebarOpen ? 'gap-3 px-3' : 'justify-center px-2'"
                    :title="!sidebarOpen ? '{{ $group['label'] }}' : ''"
                >
                    <i data-lucide="{{ $group['icon'] }}" class="h-4 w-4 shrink-0"></i>
                    <span
                        x-show="sidebarOpen"
                        x-transition:enter="transition-opacity ease-out duration-150 delay-100"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition-opacity ease-in duration-75"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="flex-1 text-left"
                    x-html="highlight(@js($group['label']))"></span>
                    <i
                        data-lucide="chevron-right"
                        x-show="sidebarOpen"
                        class="h-3.5 w-3.5 text-slate-300 transition-transform duration-200 dark:text-slate-600"
                        :class="isOpen('{{ $group['id'] }}') ? 'rotate-90' : ''"
                    ></i>
                </button>

                <div
                    x-show="sidebarOpen && isOpen('{{ $group['id'] }}')"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                    class="ml-6 mt-0.5 space-y-0.5 border-l border-slate-100 pb-1 pl-3 dark:border-white/5"
                >
                    @foreach ($group['items'] as $item)
                        <a
                            x-show="itemMatches(@js($group['label']), @js($item['label']))"
                            href="{{ $item['href'] }}"
                            class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-[13px] transition-all duration-150
                                {{ $item['active']
                                    ? 'bg-blue-50 font-medium text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                                    : ($item['href'] === '#'
                                        ? 'cursor-not-allowed text-slate-400 dark:text-slate-600'
                                        : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 dark:text-slate-500 dark:hover:bg-white/5 dark:hover:text-slate-300') }}"
                            @if($item['href'] === '#') @click.prevent="" @endif
                        >
                            <i data-lucide="{{ $item['icon'] }}" class="h-3.5 w-3.5 shrink-0"></i>
                            <span x-html="highlight(@js($item['label']))"></span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
        <div x-show="hasSearch() && !hasSearchResults()" x-cloak class="px-3 py-8 text-center">
            <i data-lucide="search-x" class="mx-auto h-5 w-5 text-slate-300"></i>
            <p class="mt-2 text-xs text-slate-400">Menu tidak ditemukan</p>
        </div>
    </nav>

    {{-- User footer --}}
    <div class="shrink-0 border-t border-slate-100 p-3 dark:border-white/5">
        <div
            class="flex items-center rounded-xl p-2.5 transition-all duration-300 hover:bg-slate-50 dark:hover:bg-white/5"
            :class="sidebarOpen ? 'gap-3' : 'justify-center'"
        >
            <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-violet-600 text-xs font-bold text-white"
                :title="!sidebarOpen ? '{{ $user?->name ?? 'Guest' }}' : ''"
            >
                {{ $initials }}
            </div>
            <div
                x-show="sidebarOpen"
                x-transition:enter="transition-opacity ease-out duration-150 delay-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-75"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="min-w-0 flex-1"
            >
                <p class="truncate text-[13px] font-semibold leading-none text-slate-800 dark:text-white">{{ $user?->name ?? 'Guest' }}</p>
                <p class="mt-0.5 truncate text-[11px] text-slate-400">{{ $user?->email ?? '' }}</p>
            </div>
            <a
                x-show="sidebarOpen"
                href="{{ $r('filament.helpdesk.auth.logout') }}"
                class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-300 transition-all hover:bg-red-50 hover:text-red-400 dark:text-slate-600 dark:hover:bg-red-500/10"
                title="Keluar"
                onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
            >
                <i data-lucide="log-out" class="h-4 w-4"></i>
            </a>
            <form id="logout-form" method="POST" action="{{ $r('filament.helpdesk.auth.logout') }}" class="hidden">
                @csrf
            </form>
        </div>
    </div>
</div>
</aside>

{{-- Backdrop (mobile) --}}
<div
    x-show="sidebarOpen"
    @click="sidebarOpen=false"
    class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
    x-transition:enter="transition duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-cloak
></div>

{{-- ═══════════════════════ MAIN ═══════════════════════ --}}
<div class="flex min-w-0 flex-1 flex-col overflow-hidden">

    {{-- Topbar --}}
    <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200/80 bg-white/80 px-4 backdrop-blur-xl transition-colors duration-300 dark:border-white/5 dark:bg-[#020617]/80 lg:px-6">

        <button
            @click="sidebarOpen=!sidebarOpen"
            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-all hover:bg-slate-100 dark:hover:bg-white/5"
        >
            <i data-lucide="menu" class="h-4 w-4"></i>
        </button>

        {{-- Search --}}
        <div class="mx-2 hidden max-w-sm flex-1 md:block">
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                <input
                    type="text"
                    x-model.debounce.50ms="searchQuery"
                    @input="sidebarOpen = true"
                    placeholder="Cari menu atau submenu..."
                    class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-4 text-sm text-slate-700 placeholder-slate-400 outline-none transition-all focus:border-blue-400 focus:ring-2 focus:ring-blue-500/15 dark:border-white/5 dark:bg-white/5 dark:text-slate-200 dark:placeholder-slate-600"
                >
            </div>
        </div>

        <div class="ml-auto flex items-center gap-1">
            {{-- Notifications --}}
            <button class="relative flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition-all hover:bg-slate-100 dark:hover:bg-white/5">
                <i data-lucide="bell" class="h-4 w-4"></i>
            </button>

            {{-- Dark mode toggle --}}
            <button
                @click="toggleTheme()"
                class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition-all hover:bg-slate-100 dark:hover:bg-white/5"
                :title="dark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
            >
                <i data-lucide="sun"  x-show="dark"  class="h-4 w-4 text-amber-400"></i>
                <i data-lucide="moon" x-show="!dark" class="h-4 w-4"></i>
            </button>

            {{-- Profile chip --}}
            <div class="flex items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-white/5 cursor-default transition-all">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-violet-600 text-xs font-bold text-white shrink-0">
                    {{ $initials }}
                </div>
                <span class="hidden text-[13px] font-semibold text-slate-700 dark:text-slate-200 sm:block">{{ $user?->name ?? 'Guest' }}</span>
                <i data-lucide="chevron-down" class="hidden h-3.5 w-3.5 text-slate-400 sm:block"></i>
            </div>
        </div>
    </header>

    {{-- Page content --}}
    <main class="flex-1 overflow-y-auto">
        <div class="w-full px-4 py-6 lg:px-6">
            {{ $slot }}
        </div>
    </main>
</div>

</div>{{-- end flex wrapper --}}

@livewireScripts

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('helpdeskLayout', () => ({
        dark: localStorage.getItem('theme') === 'dark'
            || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),
        sidebarOpen: window.innerWidth >= 1024,
        openGroups: @json($initialOpen),
        searchQuery: '',
        navSearchGroups: @js($navSearchGroups),

        init() {
            // Apply saved theme to <html> immediately
            document.documentElement.classList.toggle('dark', this.dark);

            this.$watch('dark', v => {
                document.documentElement.classList.toggle('dark', v);
                localStorage.setItem('theme', v ? 'dark' : 'light');
            });

            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },

        toggleTheme() {
            this.dark = !this.dark;
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },

        toggleGroup(id) {
            if (this.openGroups.includes(id)) {
                this.openGroups = [];
            } else {
                this.openGroups = [id];
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                });
            }
        },

        toggleSidebarGroup(id) {
            if (!this.sidebarOpen) {
                this.sidebarOpen = true;
                this.openGroups = [id];
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                });
            } else {
                this.toggleGroup(id);
            }
        },

        normalizedSearch() { return this.searchQuery.trim().toLocaleLowerCase('id'); },
        hasSearch() { return this.normalizedSearch().length > 0; },
        groupMatches(id) {
            if (!this.hasSearch()) return true;
            const group = this.navSearchGroups.find(group => group.id === id);
            if (!group) return false;
            const query = this.normalizedSearch();
            return group.label.toLocaleLowerCase('id').includes(query)
                || group.items.some(label => label.toLocaleLowerCase('id').includes(query));
        },
        hasSearchResults() { return this.navSearchGroups.some(group => this.groupMatches(group.id)); },
        itemMatches(groupLabel, itemLabel) {
            if (!this.hasSearch()) return true;
            const query = this.normalizedSearch();
            return groupLabel.toLocaleLowerCase('id').includes(query)
                || itemLabel.toLocaleLowerCase('id').includes(query);
        },
        highlight(label) {
            const escaped = String(label)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
            if (!this.hasSearch()) return escaped;
            const query = this.searchQuery.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return escaped.replace(new RegExp(`(${query})`, 'ig'), '<mark class="rounded bg-yellow-200 px-0.5 text-slate-900">$1</mark>');
        },
        isOpen(id) { return this.hasSearch() ? this.groupMatches(id) : this.openGroups.includes(id); },
    }));
});
</script>
</body>
</html>
