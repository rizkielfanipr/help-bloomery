<x-filament-panels::page>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white dark:border-blue-900">
            <div class="flex flex-col justify-between gap-5 md:flex-row md:items-center">
                <div>
                    <p class="text-sm font-semibold text-blue-100">Research &amp; Development</p>
                    <h2 class="mt-1 text-2xl font-bold">Project Workspace</h2>
                    <p class="mt-1 max-w-2xl text-sm text-blue-100">Kelola timeline dan seluruh Bill of Material dalam project yang terpisah dan terstruktur.</p>
                </div>
                @if(\App\Filament\Helpdesk\Resources\Projects\ProjectResource::canCreate())
                    <a href="{{ \App\Filament\Helpdesk\Resources\Projects\ProjectResource::getUrl('create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-blue-700 transition hover:bg-blue-50">
                        <x-heroicon-o-plus class="h-5 w-5" />
                        Buat Project
                    </a>
                @endif
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px]">
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                    <input wire:model.live.debounce.300ms="projectSearch" type="search" placeholder="Cari nama atau deskripsi project..."
                           class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                </div>
                <select wire:model.live="projectStatus" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Semua Status</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
        </section>

        @php
            $projects = $this->projects();
        @endphp
        <section class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
            @forelse($projects as $project)
                @php
                    $status = today()->lt($project->start_date) ? 'Upcoming' : (today()->gt($project->end_date) ? 'Completed' : 'Active');
                    $statusClass = match($status) {
                        'Active' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
                        'Completed' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                        default => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
                    };
                    $duration = max(1, $project->start_date->diffInDays($project->end_date) + 1);
                    $elapsed = min($duration, max(0, $project->start_date->diffInDays(today(), false) + 1));
                    $progress = $status === 'Completed' ? 100 : ($status === 'Upcoming' ? 0 : min(100, (int) round(($elapsed / $duration) * 100)));
                @endphp
                <article class="group flex min-h-72 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white transition hover:-translate-y-0.5 hover:border-blue-300 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-700">
                    <div class="h-1.5 bg-gradient-to-r from-blue-500 to-indigo-500"></div>
                    <div class="flex flex-1 flex-col p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                                <x-heroicon-o-folder-open class="h-6 w-6" />
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ $status }}</span>
                        </div>
                        <h3 class="mt-4 text-lg font-bold text-gray-900 dark:text-white">{{ $project->name }}</h3>
                        <p class="mt-1 line-clamp-2 min-h-10 text-sm leading-5 text-gray-500">{{ $project->description ?: 'Tidak ada deskripsi project.' }}</p>

                        <div class="mt-5">
                            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50">
                                <p class="text-xs text-gray-400">Product Release</p>
                                <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">{{ $project->products_count }}</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="mb-1.5 flex items-center justify-between text-xs">
                                <span class="text-gray-500">{{ $project->start_date->format('d M Y') }} – {{ $project->end_date->format('d M Y') }}</span>
                                <span class="font-bold text-blue-600">{{ $progress }}%</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-indigo-500" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center gap-2">
                            <a href="{{ \App\Filament\Helpdesk\Resources\Projects\ProjectResource::getUrl('view', ['record' => $project]) }}" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white hover:bg-blue-700">
                                Buka Project
                                <x-heroicon-o-arrow-right class="h-4 w-4" />
                            </a>
                            @if(\App\Filament\Helpdesk\Resources\Projects\ProjectResource::canEdit($project))
                                <a href="{{ \App\Filament\Helpdesk\Resources\Projects\ProjectResource::getUrl('edit', ['record' => $project]) }}" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800" title="Edit Project">
                                    <x-heroicon-o-pencil-square class="h-5 w-5" />
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-900">
                    <x-heroicon-o-folder-plus class="mx-auto h-12 w-12 text-gray-300" />
                    <h3 class="mt-3 font-bold text-gray-800 dark:text-gray-100">Belum ada project</h3>
                    <p class="mt-1 text-sm text-gray-500">Buat project terlebih dahulu sebelum mengelola BOM atau resep.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
