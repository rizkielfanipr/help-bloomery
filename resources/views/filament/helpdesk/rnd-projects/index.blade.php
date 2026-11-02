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
                <span>Daftar menampilkan project yang sedang aktif. Pilih project untuk mengelola produk, dokumen CCP, kebutuhan bahan, dan tahapan pengembangan.</span>
            </div>
        </section>

        @if($createProjectModalOpen)
            <div class="fixed inset-0 z-[130] flex items-center justify-center p-4">
                <button type="button" wire:click="closeCreateProjectModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal buat project"></button>
                <form wire:submit="saveProject" class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $editingProjectId ? 'Edit Project' : 'Buat Project Baru' }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $editingProjectId ? 'Perbarui informasi utama dan periode pelaksanaan project.' : 'Lengkapi informasi utama dan target tanggal rilis project R&D.' }}</p>
                        </div>
                        <button type="button" wire:click="closeCreateProjectModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="grid gap-4 overflow-y-auto p-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="project-type" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Tipe Project *</label>
                            @if($editingProjectId)
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-bold text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                    {{ \App\Enums\RndProjectType::from($projectType)->label() }}
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Tipe Project tidak dapat diubah setelah dibuat.</p>
                            @else
                                <select id="project-type" wire:model.live="projectType" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                    @foreach(\App\Enums\RndProjectType::cases() as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('projectType')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @if($projectType === \App\Enums\RndProjectType::Partnership->value)
                            <div class="sm:col-span-2">
                                <label for="partnership-event-name" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nama Event/Partnership *</label>
                                <input id="partnership-event-name" wire:model="partnershipEventName" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Contoh: Bloomery x Partner Launch Event">
                                @error('partnershipEventName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div x-data="{ open: false, search: '' }" class="relative sm:col-span-2" @click.outside="open = false; search = ''">
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Branch Partnership *</label>
                                <div @click="open = true; $nextTick(() => $refs.partnershipBranchSearch.focus())" class="flex min-h-11 cursor-text flex-wrap items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 dark:border-gray-600 dark:bg-gray-800">
                                    @foreach($this->activeBranches->whereIn('id', $partnershipBranchIds) as $branch)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">{{ $branch->name }}<button type="button" wire:click.stop="togglePartnershipBranchId({{ $branch->id }})" class="leading-none hover:text-red-500">×</button></span>
                                    @endforeach
                                    <input x-ref="partnershipBranchSearch" x-model="search" @focus="open = true" placeholder="{{ count($partnershipBranchIds) ? 'Tambah branch...' : 'Pilih branch...' }}" class="min-w-32 flex-1 border-none bg-transparent p-0 text-sm text-gray-700 placeholder-gray-400 outline-none ring-0 focus:ring-0 dark:text-gray-200">
                                    <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 text-gray-400" />
                                </div>
                                <div x-show="open" x-transition x-cloak class="absolute z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                    @foreach($this->activeBranches->whereNotIn('id', $partnershipBranchIds) as $branch)
                                        <button type="button" x-show="search === '' || @js(mb_strtolower($branch->name)).includes(search.toLowerCase())" wire:click="togglePartnershipBranchId({{ $branch->id }})" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-blue-50 dark:text-gray-200 dark:hover:bg-gray-700">{{ $branch->name }}</button>
                                    @endforeach
                                    @if($this->activeBranches->whereNotIn('id', $partnershipBranchIds)->isEmpty())<p class="px-3 py-3 text-sm text-gray-400">Semua branch sudah dipilih.</p>@endif
                                </div>
                                @error('partnershipBranchIds')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                @error('partnershipBranchIds.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif
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
                        @if($editingProjectId)
                            <div>
                                <label for="project-start-date" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Start Date *</label>
                                <input id="project-start-date" wire:model="projectStartDate" type="date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                @error('projectStartDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif
                        <div @class(['sm:col-span-2' => ! $editingProjectId])>
                            <label for="project-end-date" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $editingProjectId ? 'End Date' : 'Tanggal Rilis' }} *</label>
                            <input id="project-end-date" wire:model="projectEndDate" type="date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
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
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_190px_auto]">
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                    <input wire:model.live.debounce.300ms="projectSearch" type="search" placeholder="Cari nama atau deskripsi project..." aria-label="Cari nama atau deskripsi project"
                           class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                </div>
                <select wire:model.live="projectTypeFilter" aria-label="Filter tipe project" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Semua Tipe</option>
                    @foreach(\App\Enums\RndProjectType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                <div class="inline-flex rounded-lg border border-gray-300 bg-gray-50 p-1 dark:border-gray-600 dark:bg-gray-800">
                    <button type="button" wire:click="showProjectList" class="inline-flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm font-semibold transition {{ $projectView === 'list' ? 'bg-white text-blue-700 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-blue-300 dark:ring-gray-600' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}">
                        <x-heroicon-o-list-bullet class="h-4 w-4" /> Daftar
                    </button>
                    @can('view rnd project tasks')
                        <button type="button" wire:click="showProjectTasks" class="inline-flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm font-semibold transition {{ $projectView === 'tasks' ? 'bg-white text-blue-700 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-blue-300 dark:ring-gray-600' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}">
                            <x-heroicon-o-calendar-days class="h-4 w-4" /> Kalender
                        </button>
                    @else
                        <button type="button" wire:click="showProjectCalendar" class="inline-flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm font-semibold transition {{ $projectView === 'calendar' ? 'bg-white text-blue-700 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-blue-300 dark:ring-gray-600' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}">
                            <x-heroicon-o-calendar-days class="h-4 w-4" /> Kalender
                        </button>
                    @endcan
                </div>
            </div>
        </section>

        @if($projectView === 'tasks')
            @include('filament.helpdesk.rnd-projects.partials.task-calendar')
        @elseif($projectView === 'calendar')
            @php
                $calendar = $this->calendar();
            @endphp
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div>
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-calendar-days class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Kalender Rilis Project</h3>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Setiap project muncul hanya pada tanggal rilisnya.</p>
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
                                           style="grid-column: {{ $segment['dayColumn'] }} / span 1"
                                           title="{{ $project->name }} · Rilis {{ $project->end_date->format('d M Y') }}">
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
                $projects = $this->activeProjects();
            @endphp
            @if($projects->isEmpty())
                <section class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-16 text-center dark:border-gray-700 dark:bg-gray-900">
                    @if($this->hasActiveProjectFilters())
                        <x-heroicon-o-magnifying-glass class="mx-auto h-12 w-12 text-gray-300" />
                        <h3 class="mt-3 font-bold text-gray-800 dark:text-gray-100">Project aktif tidak ditemukan.</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ubah kata kunci atau tipe, atau reset filter untuk melihat seluruh project aktif.</p>
                        <button type="button" wire:click="resetProjectFilters" wire:loading.attr="disabled" wire:target="resetProjectFilters" class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                            <x-heroicon-o-arrow-path class="h-4 w-4" /> Reset Filter
                        </button>
                    @else
                        <x-heroicon-o-folder-plus class="mx-auto h-12 w-12 text-gray-300" />
                        <h3 class="mt-3 font-bold text-gray-800 dark:text-gray-100">Tidak ada project aktif saat ini.</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Project muncul di daftar ini selama periodenya mencakup hari ini.</p>
                        @if(\App\Filament\Helpdesk\Resources\Projects\ProjectResource::canCreate())
                            <button type="button" wire:click="openCreateProjectModal" class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">
                                <x-heroicon-o-plus class="h-4 w-4" /> Buat Project
                            </button>
                        @endif
                    @endif
                </section>
            @else
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" aria-label="Daftar project aktif">
                    <div aria-hidden="true" class="hidden gap-3 border-b border-gray-200 bg-gray-50 px-5 py-2.5 text-xs font-bold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400 xl:grid xl:grid-cols-[minmax(0,1fr)_7.5rem_6.5rem_6.5rem_6rem_7.75rem]">
                        <span>Project</span>
                        <span>Tipe</span>
                        <span>Periode</span>
                        <span>Isi</span>
                        <span>Progress</span>
                        <span class="text-right">Aksi</span>
                    </div>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($projects as $project)
                            @php
                                $duration = max(1, $project->start_date->diffInDays($project->end_date) + 1);
                                $elapsed = min($duration, max(0, $project->start_date->diffInDays(today(), false) + 1));
                                $progress = min(100, (int) round(($elapsed / $duration) * 100));
                                $viewUrl = \App\Filament\Helpdesk\Resources\Projects\ProjectResource::getUrl('view', ['record' => $project]);
                            @endphp
                            <li wire:key="active-project-{{ $project->id }}" class="grid gap-3 p-4 transition hover:bg-gray-50 dark:hover:bg-gray-800/40 xl:grid-cols-[minmax(0,1fr)_7.5rem_6.5rem_6.5rem_6rem_7.75rem] xl:items-center xl:px-5 xl:py-3">
                                <div class="min-w-0">
                                    <a href="{{ $viewUrl }}" class="text-sm font-bold text-gray-900 [overflow-wrap:anywhere] hover:text-blue-700 dark:text-white dark:hover:text-blue-300">{{ $project->name }}</a>
                                    @if(filled($project->description))
                                        <p class="mt-0.5 line-clamp-2 text-xs leading-5 text-gray-500 [overflow-wrap:anywhere] dark:text-gray-400">{{ $project->description }}</p>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <span class="inline-flex w-fit rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">{{ $project->project_type->label() }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-3 xl:contents">
                                    <div class="min-w-0">
                                        <p class="text-xs text-gray-400 xl:hidden">Periode</p>
                                        <p class="text-xs font-medium text-gray-700 dark:text-gray-200">
                                            <span class="whitespace-nowrap">{{ $project->start_date->format('d M Y') }}</span> – <span class="whitespace-nowrap">{{ $project->end_date->format('d M Y') }}</span>
                                        </p>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs text-gray-400">{{ match($project->project_type) { \App\Enums\RndProjectType::Premix => 'BOM Assembly', \App\Enums\RndProjectType::Partnership => 'Menu Partnership', default => 'Product Release' } }}</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ match($project->project_type) { \App\Enums\RndProjectType::Premix => $project->premix_assemblies_count, \App\Enums\RndProjectType::Partnership => $project->partnership_menus_count, default => $project->products_count } }}</p>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="mb-1 flex items-center justify-between gap-2 text-xs xl:justify-end">
                                        <span class="text-gray-400 xl:hidden">Progress</span>
                                        <span class="font-bold text-blue-600 dark:text-blue-400">{{ $progress }}%</span>
                                    </div>
                                    <div aria-hidden="true" class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-full rounded-full bg-blue-500" style="width: {{ $progress }}%"></div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 xl:justify-end">
                                    <a href="{{ $viewUrl }}" aria-label="Buka Project {{ $project->name }}" title="Buka Project" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white hover:bg-blue-700 xl:flex-none xl:p-2">
                                        <span class="xl:hidden">Buka Project</span>
                                        <x-heroicon-o-arrow-right class="h-4 w-4" />
                                    </a>
                                    @if(\App\Filament\Helpdesk\Resources\Projects\ProjectResource::canEdit($project))
                                        <button type="button" wire:click="openEditProjectModal({{ $project->id }})" wire:loading.attr="disabled" wire:target="openEditProjectModal({{ $project->id }})" aria-label="Edit Project {{ $project->name }}" title="Edit Project" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                                        </button>
                                    @endif
                                    @if(\App\Filament\Helpdesk\Resources\Projects\ProjectResource::canDelete($project))
                                        <button type="button" x-on:click="window.BloomeryConfirm.show({ title: 'Arsipkan Project?', text: @js('Project '.$project->name.' akan disembunyikan dari daftar aktif. Seluruh Menu, BOM, dan attachment tetap tersimpan.'), confirmText: 'Arsipkan Project' }).then((confirmed) => { if (confirmed) $wire.archiveProject({{ $project->id }}) })" wire:loading.attr="disabled" wire:target="archiveProject({{ $project->id }})" aria-label="Arsipkan Project {{ $project->name }}" title="Arsipkan Project" class="rounded-lg border border-amber-200 p-2 text-amber-700 hover:bg-amber-50 disabled:opacity-50 dark:border-amber-900 dark:text-amber-300 dark:hover:bg-amber-950/30">
                                            <x-heroicon-o-archive-box class="h-4 w-4" />
                                        </button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
