<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helpdesk — Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif'] },
                    colors: {
                        navy: { 950: '#020617', 900: '#0F172A', 800: '#1E293B', 700: '#334155' },
                    },
                    animation: {
                        'fade-in': 'fadeIn .2s ease-out',
                        'slide-down': 'slideDown .2s ease-out',
                    },
                    keyframes: {
                        fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                        slideDown: { '0%': { opacity: '0', transform: 'translateY(-6px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
                    },
                }
            }
        }
    </script>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        *,:before,:after{box-sizing:border-box}
        ::-webkit-scrollbar{width:4px;height:4px}
        ::-webkit-scrollbar-track{background:transparent}
        ::-webkit-scrollbar-thumb{background:#475569;border-radius:4px}
        .dark ::-webkit-scrollbar-thumb{background:#1e293b}
        [x-cloak]{display:none!important}
        .nav-sub-link{@apply block rounded-lg px-3 py-1.5 text-[13px] transition-all duration-150 cursor-pointer}
        .sidebar-w{width:256px;min-width:256px}
    </style>
</head>

<body
    x-data="app()"
    x-init="boot()"
    :class="dark ? 'dark' : ''"
    class="font-sans antialiased"
>
<div class="flex h-screen overflow-hidden bg-[#F8FAFC] dark:bg-[#020617] transition-colors duration-300">

{{-- ═══════════════════════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════════════════════ --}}
<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0 lg:w-0'"
    class="sidebar-w fixed inset-y-0 left-0 z-50 flex flex-col border-r border-slate-200 bg-white dark:border-white/5 dark:bg-[#0F172A] transition-transform duration-300 ease-in-out lg:relative lg:flex lg:shrink-0"
>
    {{-- Logo --}}
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-100 dark:border-white/5 px-5">
        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 shadow-lg shadow-blue-500/30">
            <i data-lucide="zap" class="h-5 w-5 text-white"></i>
        </div>
        <div>
            <p class="text-[15px] font-bold text-slate-900 dark:text-white leading-none">Helpdesk</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Enterprise Suite</p>
        </div>
        <button @click="sidebarOpen=false" class="ml-auto flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 lg:hidden">
            <i data-lucide="x" class="h-4 w-4"></i>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto py-3 px-2.5 space-y-0.5">

        {{-- Single link --}}
        <a href="#" @click.prevent="page='dashboard'" :class="page==='dashboard' ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 font-semibold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-white/5 font-medium'" class="flex items-center gap-3 rounded-xl px-3 py-2 text-[13px] transition-all duration-150 cursor-pointer">
            <i data-lucide="layout-dashboard" class="h-4 w-4 shrink-0"></i>
            Dashboard
        </a>

        {{-- Groups --}}
        <template x-for="group in navGroups" :key="group.id">
            <div>
                <button @click="toggleGroup(group.id)" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-[13px] font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-white/5 transition-all duration-150">
                    <i :data-lucide="group.icon" class="h-4 w-4 shrink-0"></i>
                    <span class="flex-1 text-left" x-text="group.label"></span>
                    <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-slate-300 dark:text-slate-600 transition-transform duration-200" :class="isOpen(group.id) ? 'rotate-90' : ''"></i>
                </button>

                <div x-show="isOpen(group.id)" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1" class="mt-0.5 ml-6 pl-3 border-l border-slate-100 dark:border-white/5 space-y-0.5 pb-1">
                    <template x-for="item in group.items" :key="item.id">
                        <a href="#" @click.prevent="page=item.id" :class="page===item.id ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-500/10 font-medium' : 'text-slate-500 dark:text-slate-500 hover:text-slate-800 dark:hover:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'" class="block rounded-lg px-3 py-1.5 text-[13px] transition-all duration-150 cursor-pointer" x-text="item.label"></a>
                    </template>
                </div>
            </div>
        </template>

    </nav>

    {{-- User --}}
    <div class="shrink-0 border-t border-slate-100 dark:border-white/5 p-3">
        <div class="flex items-center gap-3 rounded-xl p-2.5 hover:bg-slate-50 dark:hover:bg-white/5 cursor-pointer transition-all duration-150">
            <div class="h-8 w-8 rounded-full bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center text-white text-xs font-bold shrink-0">SA</div>
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-semibold text-slate-800 dark:text-white leading-none truncate">Super Admin</p>
                <p class="text-[11px] text-slate-400 mt-0.5 truncate">admin@bloomery.org</p>
            </div>
            <i data-lucide="log-out" class="h-4 w-4 text-slate-300 dark:text-slate-600 shrink-0"></i>
        </div>
    </div>
</aside>

{{-- Sidebar backdrop (mobile) --}}
<div x-show="sidebarOpen" @click="sidebarOpen=false" class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden" x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

{{-- ═══════════════════════════════════════════════════════════
     MAIN
═══════════════════════════════════════════════════════════ --}}
<div class="flex min-w-0 flex-1 flex-col overflow-hidden">

    {{-- ── Topbar ────────────────────────────────────────── --}}
    <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200/80 bg-white/80 dark:border-white/5 dark:bg-[#020617]/80 backdrop-blur-xl px-4 lg:px-6">

        <button @click="sidebarOpen=!sidebarOpen" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5 transition-all">
            <i data-lucide="menu" class="h-4 w-4"></i>
        </button>

        {{-- Breadcrumb --}}
        <div class="hidden sm:flex items-center gap-1.5 text-sm">
            <span class="text-slate-400 dark:text-slate-500">Helpdesk</span>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-slate-300 dark:text-slate-700"></i>
            <span class="font-medium text-slate-700 dark:text-slate-200">Dashboard</span>
        </div>

        {{-- Search --}}
        <div class="mx-2 flex-1 max-w-sm hidden md:block">
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
                <input type="text" placeholder="Cari tiket, pengguna..." class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-4 text-sm text-slate-700 placeholder-slate-400 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-500/15 dark:border-white/5 dark:bg-white/5 dark:text-slate-200 dark:placeholder-slate-600 transition-all">
            </div>
        </div>

        <div class="ml-auto flex items-center gap-1">
            {{-- Notifications --}}
            <button class="relative flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5 transition-all">
                <i data-lucide="bell" class="h-4 w-4"></i>
                <span class="absolute right-1.5 top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white ring-2 ring-white dark:ring-[#020617]">3</span>
            </button>

            {{-- Theme toggle --}}
            <button @click="toggleTheme()" class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5 transition-all">
                <i data-lucide="sun" x-show="dark" class="h-4 w-4 text-amber-400"></i>
                <i data-lucide="moon" x-show="!dark" class="h-4 w-4"></i>
            </button>

            {{-- Profile --}}
            <button class="flex items-center gap-2.5 rounded-xl pl-1 pr-2.5 py-1.5 hover:bg-slate-100 dark:hover:bg-white/5 transition-all">
                <div class="h-7 w-7 rounded-full bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center text-white text-xs font-bold">SA</div>
                <span class="hidden sm:block text-[13px] font-semibold text-slate-700 dark:text-slate-200">Super Admin</span>
                <i data-lucide="chevron-down" class="h-3.5 w-3.5 text-slate-400 hidden sm:block"></i>
            </button>
        </div>
    </header>

    {{-- ── Page Content ──────────────────────────────────── --}}
    <main class="flex-1 overflow-y-auto p-4 lg:p-6">
    <div class="mx-auto max-w-7xl space-y-5">

        {{-- Welcome Banner --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 p-6 shadow-xl shadow-blue-900/20">
            <div class="pointer-events-none absolute -right-12 -top-12 h-48 w-48 rounded-full bg-white/5"></div>
            <div class="pointer-events-none absolute -bottom-8 right-1/4 h-36 w-36 rounded-full bg-indigo-500/20"></div>
            <div class="pointer-events-none absolute right-8 top-4 h-20 w-20 rounded-full bg-white/5"></div>

            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-blue-200">Kamis, 28 Mei 2026</p>
                    <h1 class="mt-1 text-2xl font-bold text-white lg:text-3xl">Selamat datang, Super Admin! 👋</h1>
                    <p class="mt-1 text-sm text-blue-200/80">Berikut adalah ringkasan aktivitas helpdesk hari ini.</p>
                </div>
                <div class="flex shrink-0 gap-2.5">
                    <div class="rounded-2xl bg-white/10 px-4 py-3 text-center backdrop-blur-sm ring-1 ring-white/10">
                        <p class="text-xl font-bold text-white">132</p>
                        <p class="mt-0.5 text-[11px] font-medium text-blue-200">Diajukan</p>
                    </div>
                    <div class="rounded-2xl bg-white/10 px-4 py-3 text-center backdrop-blur-sm ring-1 ring-white/10">
                        <p class="text-xl font-bold text-white">38</p>
                        <p class="mt-0.5 text-[11px] font-medium text-blue-200">Dikerjakan</p>
                    </div>
                    <div class="rounded-2xl bg-emerald-400/20 px-4 py-3 text-center backdrop-blur-sm ring-1 ring-emerald-400/20">
                        <p class="text-xl font-bold text-white">16</p>
                        <p class="mt-0.5 text-[11px] font-medium text-emerald-200">Selesai</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats Grid --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <template x-for="s in stats" :key="s.label">
                <div class="group relative overflow-hidden rounded-2xl border bg-white p-5 shadow-sm ring-1 ring-transparent hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:bg-[#0F172A] dark:border-white/5 dark:shadow-none dark:hover:ring-white/10"
                     :class="'border-'+s.color+'-100 dark:border-white/5'">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400" x-text="s.label"></p>
                            <p class="mt-1.5 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white" x-text="s.value"></p>
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-600" x-text="s.sub"></p>
                        </div>
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="'bg-'+s.color+'/10'">
                            <i :data-lucide="s.icon" class="h-5 w-5" :class="'text-'+s.color"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <template x-if="s.change >= 0">
                            <span class="inline-flex items-center gap-0.5 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                                <i data-lucide="trending-up" class="h-3 w-3"></i>
                                <span x-text="'+'+s.change+'%'"></span>
                            </span>
                        </template>
                        <template x-if="s.change < 0">
                            <span class="inline-flex items-center gap-0.5 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-500 dark:bg-red-500/10 dark:text-red-400">
                                <i data-lucide="trending-down" class="h-3 w-3"></i>
                                <span x-text="s.change+'%'"></span>
                            </span>
                        </template>
                        <span class="text-[11px] text-slate-400 dark:text-slate-600">dari bulan lalu</span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Charts + Table Row --}}
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">

            {{-- Ticket Table (2/3) --}}
            <div class="xl:col-span-2 rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-white/5 dark:bg-[#0F172A]">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-white/5 px-5 py-4">
                    <div>
                        <h3 class="font-semibold text-slate-800 dark:text-white">Tiket Terbaru</h3>
                        <p class="text-xs text-slate-400 mt-0.5">8 permintaan terakhir</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:border-white/5 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10 transition-all">
                            <i data-lucide="filter" class="h-3 w-3"></i> Filter
                        </button>
                        <button class="flex items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-all">
                            <i data-lucide="arrow-right" class="h-3 w-3"></i> Lihat semua
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-50 dark:border-white/5">
                                <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Ref</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Pemohon</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Divisi</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Status</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600">Jadwal</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-600"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-white/[0.03]">
                            <template x-for="t in tickets" :key="t.ref">
                                <tr class="group hover:bg-slate-50/70 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-5 py-3.5">
                                        <span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400" x-text="t.ref"></span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-2.5">
                                            <div class="h-7 w-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold shrink-0" :style="'background:'+t.avatar_color">
                                                <span x-text="t.requestor.slice(0,2).toUpperCase()"></span>
                                            </div>
                                            <span class="text-[13px] font-medium text-slate-700 dark:text-slate-200" x-text="t.requestor"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="text-[13px] text-slate-500 dark:text-slate-400" x-text="t.division"></span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset" :class="statusClass(t.status)" x-text="t.status_label"></span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="text-[12px] text-slate-400 dark:text-slate-500" x-text="t.date"></span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <button class="invisible group-hover:visible flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 transition-all">
                                            <i data-lucide="more-horizontal" class="h-4 w-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                {{-- Pagination --}}
                <div class="flex items-center justify-between border-t border-slate-100 dark:border-white/5 px-5 py-3.5">
                    <p class="text-xs text-slate-400">Menampilkan 1–8 dari 186 tiket</p>
                    <div class="flex items-center gap-1">
                        <button class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 disabled:opacity-40 transition-all"><i data-lucide="chevron-left" class="h-4 w-4"></i></button>
                        <button class="flex h-7 min-w-7 items-center justify-center rounded-lg bg-blue-600 px-2 text-xs font-semibold text-white">1</button>
                        <button class="flex h-7 min-w-7 items-center justify-center rounded-lg text-xs font-medium text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5 transition-all px-2">2</button>
                        <button class="flex h-7 min-w-7 items-center justify-center rounded-lg text-xs font-medium text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5 transition-all px-2">3</button>
                        <span class="px-1 text-slate-300 dark:text-slate-700">···</span>
                        <button class="flex h-7 min-w-7 items-center justify-center rounded-lg text-xs font-medium text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5 transition-all px-2">24</button>
                        <button class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 transition-all"><i data-lucide="chevron-right" class="h-4 w-4"></i></button>
                    </div>
                </div>
            </div>

            {{-- Right column: Donut + Activity --}}
            <div class="flex flex-col gap-4">

                {{-- Status Donut --}}
                <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-[#0F172A]">
                    <div class="mb-4">
                        <h3 class="font-semibold text-slate-800 dark:text-white">Ringkasan Status</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Distribusi tiket saat ini</p>
                    </div>
                    <div class="relative flex justify-center" style="height:170px">
                        <canvas id="donutChart"></canvas>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <p class="text-2xl font-extrabold text-slate-800 dark:text-white">186</p>
                            <p class="text-[11px] text-slate-400">Total Tiket</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2.5">
                        <template x-for="(s, i) in statusMeta" :key="s.label">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-2.5 w-2.5 rounded-full shrink-0" :style="'background:'+s.color"></div>
                                    <span class="text-[13px] text-slate-600 dark:text-slate-300" x-text="s.label"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[13px] font-bold text-slate-800 dark:text-white" x-text="s.count"></span>
                                    <span class="w-8 text-right text-[11px] text-slate-400" x-text="s.pct+'%'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Activity Feed --}}
                <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-[#0F172A] flex-1">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-slate-800 dark:text-white">Aktivitas Terbaru</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Riwayat perbaikan terkini</p>
                        </div>
                        <button class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">Semua</button>
                    </div>
                    <ol class="relative space-y-4">
                        <template x-for="(a, i) in activities" :key="i">
                            <li class="relative flex gap-3">
                                <div class="relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full ring-4 ring-white dark:ring-[#0F172A]" :class="a.done ? 'bg-emerald-500' : 'bg-amber-400'">
                                    <template x-if="a.done">
                                        <i data-lucide="check" class="h-3.5 w-3.5 text-white"></i>
                                    </template>
                                    <template x-if="!a.done">
                                        <i data-lucide="wrench" class="h-3.5 w-3.5 text-white"></i>
                                    </template>
                                </div>
                                <div x-show="!$last(activities, i)" class="absolute left-3.5 top-7 bottom-0 w-px bg-slate-100 dark:bg-white/5"></div>
                                <div class="min-w-0 flex-1 pt-0.5 pb-3">
                                    <p class="text-[13px] font-medium leading-snug text-slate-700 dark:text-slate-200" x-text="a.title"></p>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <span class="font-mono text-[11px] font-bold text-blue-600 dark:text-blue-400" x-text="a.ref"></span>
                                        <span class="text-slate-200 dark:text-slate-700">·</span>
                                        <span class="text-[11px] text-slate-400 dark:text-slate-500" x-text="a.time"></span>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ol>
                </div>

            </div>
        </div>

        {{-- Trend Chart (full width) --}}
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-[#0F172A]">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-slate-800 dark:text-white">Tren Tiket</h3>
                    <p class="text-xs text-slate-400 mt-0.5">30 hari terakhir</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="trendPeriod=30; updateTrend()" :class="trendPeriod===30 ? 'bg-blue-600 text-white' : 'bg-slate-50 text-slate-500 hover:bg-slate-100 dark:bg-white/5 dark:text-slate-400 dark:hover:bg-white/10'" class="rounded-xl px-3 py-1.5 text-xs font-medium transition-all">30 Hari</button>
                    <button @click="trendPeriod=7; updateTrend()" :class="trendPeriod===7 ? 'bg-blue-600 text-white' : 'bg-slate-50 text-slate-500 hover:bg-slate-100 dark:bg-white/5 dark:text-slate-400 dark:hover:bg-white/10'" class="rounded-xl px-3 py-1.5 text-xs font-medium transition-all">7 Hari</button>
                </div>
            </div>
            <div style="height:220px">
                <canvas id="lineChart"></canvas>
            </div>
        </div>

    </div>
    </main>
</div>

</div>{{-- end flex wrapper --}}

{{-- ═══════════════════════════════════════════════════════════
     SCRIPTS
═══════════════════════════════════════════════════════════ --}}
<script>
function app() {
    return {
        dark: true,
        sidebarOpen: true,
        page: 'dashboard',
        openGroups: ['casual'],
        trendPeriod: 30,
        charts: {},

        navGroups: [
            { id:'casual', label:'Casual', icon:'users', items:[
                {id:'posisi-casual',label:'Posisi Casual'},
                {id:'pengaduan-casual',label:'Pengaduan Casual'},
                {id:'monitoring-casual',label:'Monitoring Casual'},
                {id:'jadwal-shift',label:'Jadwal Shift'},
            ]},
            { id:'driver', label:'Driver', icon:'truck', items:[
                {id:'permintaan-driver',label:'Permintaan Driver'},
                {id:'pengaduan-driver',label:'Pengaduan Driver'},
                {id:'monitoring-perjalanan',label:'Monitoring Perjalanan'},
                {id:'kendaraan',label:'Kendaraan'},
            ]},
            { id:'technician', label:'Technician', icon:'wrench', items:[
                {id:'permintaan-teknisi',label:'Permintaan Teknisi'},
                {id:'pengaduan-teknisi',label:'Pengaduan Teknisi'},
                {id:'monitoring-perbaikan',label:'Monitoring Perbaikan'},
                {id:'jadwal-teknisi',label:'Jadwal Teknisi'},
            ]},
            { id:'purchasing', label:'Purchasing', icon:'shopping-cart', items:[
                {id:'permintaan-pembelian',label:'Permintaan Pembelian'},
                {id:'approval-pembelian',label:'Approval Pembelian'},
                {id:'vendor',label:'Vendor'},
                {id:'monitoring-barang',label:'Monitoring Barang'},
            ]},
            { id:'it', label:'Information Technology', icon:'monitor', items:[
                {id:'permintaan-it',label:'Permintaan IT'},
                {id:'pengaduan-it',label:'Pengaduan IT'},
                {id:'asset-it',label:'Asset IT'},
                {id:'monitoring-jaringan',label:'Monitoring Jaringan'},
            ]},
            { id:'finance', label:'Finance', icon:'credit-card', items:[
                {id:'permintaan-finance',label:'Permintaan Finance'},
                {id:'approval-finance',label:'Approval Finance'},
                {id:'pengeluaran',label:'Pengeluaran'},
                {id:'laporan-keuangan',label:'Laporan Keuangan'},
            ]},
            { id:'helpdesk-menu', label:'Helpdesk', icon:'headphones', items:[
                {id:'semua-tiket',label:'Semua Tiket'},
                {id:'tiket-diproses',label:'Tiket Diproses'},
                {id:'tiket-selesai',label:'Tiket Selesai'},
                {id:'faq',label:'FAQ'},
                {id:'pengumuman',label:'Pengumuman'},
            ]},
            { id:'management', label:'Management Access', icon:'shield', items:[
                {id:'pengguna',label:'Pengguna'},
                {id:'role-permission',label:'Role & Permission'},
                {id:'setting',label:'Setting'},
            ]},
        ],

        stats: [
            { label:'Total Tiket', value:'186', sub:'semua permintaan', change:12, color:'blue-500', icon:'ticket' },
            { label:'Tiket Baru',  value:'132', sub:'bulan ini',        change:8,  color:'sky-500',  icon:'plus-circle' },
            { label:'Sedang Dikerjakan', value:'38', sub:'tiket aktif', change:-3, color:'amber-500', icon:'wrench' },
            { label:'Tiket Selesai', value:'16', sub:'total diselesaikan', change:23, color:'emerald-500', icon:'check-circle' },
        ],

        statusMeta: [
            { label:'Diajukan',   color:'#3b82f6', count:132, pct:71 },
            { label:'Dikerjakan', color:'#f59e0b', count:38,  pct:20 },
            { label:'Garansi',    color:'#8b5cf6', count:16,  pct:9  },
            { label:'Selesai',    color:'#10b981', count:0,   pct:0  },
        ],

        tickets: [
            { ref:'SR-0001', requestor:'Budi Santoso',  avatar_color:'#3b82f6', division:'Teknisi', status:'submitted',   status_label:'Diajukan',   date:'01 Jun 2026' },
            { ref:'SR-0002', requestor:'Siti Rahayu',   avatar_color:'#8b5cf6', division:'Finance', status:'in_progress', status_label:'Dikerjakan', date:'31 Mei 2026' },
            { ref:'SR-0003', requestor:'Ahmad Fauzi',   avatar_color:'#10b981', division:'IT',      status:'warranty',   status_label:'Garansi',    date:'30 Mei 2026' },
            { ref:'SR-0004', requestor:'Dewi Lestari',  avatar_color:'#f59e0b', division:'Casual',  status:'completed',  status_label:'Selesai',    date:'29 Mei 2026' },
            { ref:'SR-0005', requestor:'Eko Prasetyo',  avatar_color:'#ef4444', division:'Driver',  status:'submitted',  status_label:'Diajukan',   date:'28 Mei 2026' },
            { ref:'SR-0006', requestor:'Fajar Nugroho', avatar_color:'#06b6d4', division:'Teknisi', status:'in_progress',status_label:'Dikerjakan', date:'28 Mei 2026' },
            { ref:'SR-0007', requestor:'Gita Permata',  avatar_color:'#d946ef', division:'IT',      status:'submitted',  status_label:'Diajukan',   date:'27 Mei 2026' },
            { ref:'SR-0008', requestor:'Hendra Wijaya', avatar_color:'#f97316', division:'Finance', status:'completed',  status_label:'Selesai',    date:'26 Mei 2026' },
        ],

        activities: [
            { done:true,  title:'Perbaikan selesai oleh Rizki Elfani',    ref:'SR-0003', time:'5 menit lalu' },
            { done:false, title:'Perbaikan dimulai oleh Andi Firmansyah', ref:'SR-0002', time:'22 menit lalu' },
            { done:true,  title:'Perbaikan selesai oleh Dewi Lestari',    ref:'SR-0004', time:'1 jam lalu' },
            { done:false, title:'Perbaikan dimulai oleh Rizki Elfani',    ref:'SR-0006', time:'2 jam lalu' },
            { done:true,  title:'Perbaikan selesai oleh Andi Firmansyah', ref:'SR-0001', time:'3 jam lalu' },
        ],

        toggleGroup(id) {
            this.openGroups.includes(id)
                ? (this.openGroups = this.openGroups.filter(g => g !== id))
                : this.openGroups.push(id);
        },
        isOpen(id) { return this.openGroups.includes(id); },

        statusClass(s) {
            return {
                submitted:   'bg-blue-50   text-blue-700   ring-blue-600/20   dark:bg-blue-500/10   dark:text-blue-400   dark:ring-blue-500/20',
                in_progress: 'bg-amber-50  text-amber-700  ring-amber-600/20  dark:bg-amber-500/10  dark:text-amber-400  dark:ring-amber-500/20',
                warranty:    'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-500/10 dark:text-violet-400 dark:ring-violet-500/20',
                completed:   'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20',
            }[s] || '';
        },

        toggleTheme() {
            this.dark = !this.dark;
            this.$nextTick(() => {
                lucide.createIcons();
                this.initCharts();
            });
        },

        boot() {
            this.$nextTick(() => {
                lucide.createIcons();
                this.initCharts();
            });
            this.$watch('dark', () => this.$nextTick(() => lucide.createIcons()));
        },

        // ── chart helpers ─────────────────────────────────────
        isDark() { return this.dark; },

        gridColor() { return this.isDark() ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)'; },
        tickColor() { return this.isDark() ? '#475569' : '#94a3b8'; },
        ttBg()     { return this.isDark() ? '#1e293b' : '#ffffff'; },
        ttText()   { return this.isDark() ? '#e2e8f0' : '#0f172a'; },
        ttMuted()  { return this.isDark() ? '#94a3b8' : '#64748b'; },
        ttBorder() { return this.isDark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)'; },

        genTrend(days) {
            const base = [2,4,3,6,5,8,7,9,6,11,8,13,10,14,9,12,15,11,16,13,18,14,17,20,16,19,22,18,21,24];
            return base.slice(-days);
        },

        updateTrend() {
            if (!this.charts.line) return;
            const data = this.genTrend(this.trendPeriod);
            this.charts.line.data.labels = Array.from({length: this.trendPeriod}, (_, i) => {
                const d = new Date(); d.setDate(d.getDate() - (this.trendPeriod - 1 - i));
                return d.toLocaleDateString('id-ID', {day:'2-digit', month:'short'});
            });
            this.charts.line.data.datasets[0].data = data;
            this.charts.line.update();
        },

        initCharts() {
            // Destroy old
            Object.values(this.charts).forEach(c => c && c.destroy());
            this.charts = {};

            // ── Donut ───────────────────────────────────────
            const dEl = document.getElementById('donutChart');
            if (dEl) {
                this.charts.donut = new Chart(dEl, {
                    type: 'doughnut',
                    data: {
                        labels: ['Diajukan','Dikerjakan','Garansi','Selesai'],
                        datasets: [{
                            data: [132, 38, 16, 0],
                            backgroundColor: ['#3b82f6','#f59e0b','#8b5cf6','#10b981'],
                            borderWidth: 0,
                            hoverOffset: 6,
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        cutout: '75%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: this.ttBg(), titleColor: this.ttText(),
                                bodyColor: this.ttMuted(), borderColor: this.ttBorder(),
                                borderWidth: 1, cornerRadius: 10, padding: 10,
                            }
                        }
                    }
                });
            }

            // ── Line ─────────────────────────────────────────
            const lEl = document.getElementById('lineChart');
            if (lEl) {
                const ctx = lEl.getContext('2d');
                const grad = ctx.createLinearGradient(0, 0, 0, 220);
                grad.addColorStop(0, 'rgba(37,99,235,0.2)');
                grad.addColorStop(1, 'rgba(37,99,235,0)');

                const days = this.trendPeriod;
                const labels = Array.from({length: days}, (_, i) => {
                    const d = new Date(); d.setDate(d.getDate() - (days - 1 - i));
                    return d.toLocaleDateString('id-ID', {day:'2-digit', month:'short'});
                });

                this.charts.line = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Tiket',
                            data: this.genTrend(days),
                            borderColor: '#2563eb',
                            backgroundColor: grad,
                            fill: true, tension: 0.4,
                            borderWidth: 2.5,
                            pointRadius: 0, pointHoverRadius: 5,
                            pointHoverBackgroundColor: '#2563eb',
                            pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2,
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: this.ttBg(), titleColor: this.ttText(),
                                bodyColor: this.ttMuted(), borderColor: this.ttBorder(),
                                borderWidth: 1, cornerRadius: 10, padding: 12,
                                callbacks: { label: i => `  ${i.formattedValue} tiket` }
                            }
                        },
                        scales: {
                            x: {
                                grid: { color: this.gridColor(), drawBorder: false },
                                ticks: { color: this.tickColor(), font: { size: 11 }, maxTicksLimit: 8 },
                                border: { display: false },
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: this.gridColor(), drawBorder: false },
                                ticks: { color: this.tickColor(), font: { size: 11 }, precision: 0, stepSize: 5 },
                                border: { display: false },
                            }
                        }
                    }
                });
            }
        }
    }
}

// Helper for x-for $last
function $last(arr, i) { return i === arr.length - 1; }
</script>
</body>
</html>
