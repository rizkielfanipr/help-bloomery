<x-filament-panels::page>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col justify-between gap-5 p-5 sm:p-6 md:flex-row md:items-center">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                        <x-heroicon-o-folder-open class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Research &amp; Development</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">Project Workspace</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">Kelola timeline, Product Release, sales projection, dan seluruh Bill of Material dalam workspace project yang terstruktur.</p>
                    </div>
                </div>
                @if(\App\Filament\Helpdesk\Resources\Projects\ProjectResource::canCreate())
                    <button type="button" wire:click="openCreateProjectModal" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700">
                        <x-heroicon-o-plus class="h-5 w-5" /> Buat Project
                    </button>
                @endif
            </div>
            <div class="flex items-center gap-2 border-t border-gray-200 bg-gray-50/60 px-5 py-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800/30 dark:text-gray-400 sm:px-6">
                <x-heroicon-o-information-circle class="h-4 w-4 shrink-0 text-blue-500" />
                <span>Pilih project untuk mengelola produk, dokumen CCP, kebutuhan bahan, dan tahapan pengembangan.</span>
            </div>
        </section>

        @if($createProjectModalOpen)
            <div class="fixed inset-0 z-[130] flex items-center justify-center p-4">
                <button type="button" wire:click="closeCreateProjectModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal buat project"></button>
                <form wire:submit="saveProject" class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $editingProjectId ? 'Edit Project' : 'Buat Project Baru' }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $editingProjectId ? 'Perbarui informasi utama dan periode pelaksanaan project.' : 'Lengkapi informasi utama dan periode pelaksanaan project R&D.' }}</p>
                        </div>
                        <button type="button" wire:click="closeCreateProjectModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="grid gap-4 overflow-y-auto p-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nama Project *</label>
                            <input wire:model="projectName" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Contoh: Pengembangan Menu Seasonal">
                            @error('projectName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Deskripsi Project</label>
                            <textarea wire:model="projectDescription" rows="4" class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Jelaskan tujuan, ruang lingkup, dan hasil yang diharapkan..."></textarea>
                            @error('projectDescription')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Start Date *</label>
                            <input wire:model="projectStartDate" type="date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @error('projectStartDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">End Date *</label>
                            <input wire:model="projectEndDate" type="date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @error('projectEndDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                        <button type="button" wire:click="closeCreateProjectModal" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveProject" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveProject">{{ $editingProjectId ? 'Simpan Perubahan' : 'Buat Project' }}</span><span wire:loading wire:target="saveProject">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px_auto]">
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
                <div class="inline-flex rounded-lg border border-gray-300 bg-gray-50 p-1 dark:border-gray-600 dark:bg-gray-800">
                    <button type="button" wire:click="showProjectList" class="inline-flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm font-semibold transition {{ $projectView === 'list' ? 'bg-white text-blue-700 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-blue-300 dark:ring-gray-600' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}">
                        <x-heroicon-o-squares-2x2 class="h-4 w-4" /> Daftar
                    </button>
                    <button type="button" wire:click="showProjectCalendar" class="inline-flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm font-semibold transition {{ $projectView === 'calendar' ? 'bg-white text-blue-700 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-blue-300 dark:ring-gray-600' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}">
                        <x-heroicon-o-calendar-days class="h-4 w-4" /> Kalender
                    </button>
                </div>
            </div>
        </section>

        @if($projectView === 'calendar')
            @php
                $calendar = $this->calendar();
            @endphp
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div>
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-calendar-days class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Timeline Project</h3>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Setiap blok menunjukkan periode project dari tanggal mulai sampai tanggal selesai.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousCalendarMonth" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Bulan sebelumnya">
                            <x-heroicon-o-chevron-left class="h-4 w-4" />
                        </button>
                        <button type="button" wire:click="currentCalendarMonth" class="min-w-36 rounded-lg border border-gray-300 px-3 py-2 text-sm font-bold text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-800">{{ $calendar['monthLabel'] }}</button>
                        <button type="button" wire:click="nextCalendarMonth" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Bulan berikutnya">
                            <x-heroicon-o-chevron-right class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <div class="min-w-[760px]">
                        <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60">
                            @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dayName)
                                <div class="border-r border-gray-200 px-3 py-2 text-center text-xs font-bold uppercase tracking-wide text-gray-500 last:border-r-0 dark:border-gray-700">{{ $dayName }}</div>
                            @endforeach
                        </div>
                        @foreach($calendar['weeks'] as $weekIndex => $week)
                            <div class="border-b border-gray-200 last:border-b-0 dark:border-gray-700" wire:key="calendar-week-{{ $weekIndex }}">
                                <div class="grid grid-cols-7">
                                    @foreach($week['dates'] as $day)
                                        <div class="min-h-10 border-r border-gray-100 px-2 py-2 text-right text-xs last:border-r-0 dark:border-gray-800 {{ $day['isCurrentMonth'] ? 'text-gray-700 dark:text-gray-200' : 'bg-gray-50/70 text-gray-300 dark:bg-gray-800/30 dark:text-gray-600' }}">
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full {{ $day['isToday'] ? 'bg-blue-600 font-bold text-white' : '' }}">{{ $day['date']->day }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="grid grid-cols-7 gap-y-1 px-1 pb-2">
                                    @foreach($week['projects'] as $segment)
                                        @php
                                            $project = $segment['project'];
                                            $status = today()->lt($project->start_date) ? 'Upcoming' : (today()->gt($project->end_date) ? 'Completed' : 'Active');
                                            $barClass = match($status) {
                                                'Active' => 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-200',
                                                'Completed' => 'border-gray-300 bg-gray-100 text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300',
                                                default => 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-950/60 dark:text-amber-200',
                                            };
                                        @endphp
                                        <a href="{{ \App\Filament\Helpdesk\Resources\Projects\ProjectResource::getUrl('view', ['record' => $project]) }}"
                                           class="mx-0.5 truncate rounded-md border px-2 py-1.5 text-xs font-bold hover:brightness-95 {{ $barClass }}"
                                           style="grid-column: {{ $segment['startColumn'] }} / span {{ $segment['daySpan'] }}"
                                           title="{{ $project->name }} · {{ $project->start_date->format('d M Y') }} – {{ $project->end_date->format('d M Y') }}">
                                            {{ $project->name }}
                                        </a>
                                    @endforeach
                                    @if(count($week['projects']) === 0)
                                        <div class="col-span-7 h-6"></div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @else
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
                                <button type="button" wire:click="openEditProjectModal({{ $project->id }})" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800" title="Edit Project">
                                    <x-heroicon-o-pencil-square class="h-5 w-5" />
                                </button>
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
        @endif
    </div>
</x-filament-panels::page>
