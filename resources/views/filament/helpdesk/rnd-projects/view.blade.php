<x-filament-panels::page>
    @php
        $project = $this->record;
        $status = today()->lt($project->start_date) ? 'Upcoming' : (today()->gt($project->end_date) ? 'Completed' : 'Active');
        $canManage = \App\Filament\Helpdesk\Resources\Projects\ProjectResource::canEdit($project);
        $canExportKitchenBom = auth()->user()?->hasRole('SUPERADMIN') || auth()->user()?->can('export kitchen bill of materials');
        $canExportStoreBom = auth()->user()?->hasRole('SUPERADMIN') || auth()->user()?->can('export store bill of materials');
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
        $label = 'mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200';
    @endphp

    <div class="space-y-6" x-data="{ productFormOpen: false }"
         @open-product-form.window="productFormOpen = true"
         @close-product-form.window="productFormOpen = false"
         @keydown.escape.window="productFormOpen = false">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-5 dark:border-gray-700 sm:p-6">
                <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-start">
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                            <x-heroicon-o-folder-open class="h-6 w-6" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ \App\Filament\Helpdesk\Resources\Projects\ProjectResource::getUrl('index') }}" class="text-xs font-bold uppercase tracking-wider text-blue-600 hover:text-blue-700 dark:text-blue-400">Project R&amp;D</a>
                                <span class="text-gray-300 dark:text-gray-600">/</span>
                                <span @class([
                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' => $status === 'Active',
                                    'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' => $status === 'Upcoming',
                                    'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => $status === 'Completed',
                                ])>
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $status }}
                                </span>
                            </div>
                            <h2 class="mt-2 text-2xl font-bold text-gray-950 dark:text-white sm:text-3xl">{{ $project->name }}</h2>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $project->description ?: 'Deskripsi project belum ditambahkan.' }}</p>
                        </div>
                    </div>
                    @if($canManage)
                        <button type="button" wire:click="openEditProjectModal" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700">
                            <x-heroicon-o-pencil-square class="h-5 w-5" /> Edit Project
                        </button>
                    @endif
                </div>
            </div>

            <div class="grid divide-y divide-gray-200 dark:divide-gray-700 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                <div class="flex items-start gap-3 p-5">
                    <x-heroicon-o-calendar-days class="mt-0.5 h-5 w-5 shrink-0 text-blue-500" />
                    <div><p class="text-xs font-bold uppercase tracking-wide text-gray-400">Periode Project</p><p class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $project->start_date->format('d M Y') }} – {{ $project->end_date->format('d M Y') }}</p><p class="mt-1 text-xs text-gray-500">{{ $project->start_date->diffInDays($project->end_date) + 1 }} hari kalender</p></div>
                </div>
                <div class="flex items-start gap-3 p-5">
                    <x-heroicon-o-cube class="mt-0.5 h-5 w-5 shrink-0 text-violet-500" />
                    <div><p class="text-xs font-bold uppercase tracking-wide text-gray-400">Menu Release</p><p class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $project->products->count() }} Menu</p><p class="mt-1 text-xs text-gray-500">Menu yang terdaftar di project</p></div>
                </div>
                <div class="flex items-start gap-3 border-t border-gray-200 p-5 dark:border-gray-700 sm:border-l-0 xl:border-l xl:border-t-0">
                    <x-heroicon-o-user-circle class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                    <div><p class="text-xs font-bold uppercase tracking-wide text-gray-400">Project Owner</p><p class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $project->creator?->name ?? 'Belum ditentukan' }}</p><p class="mt-1 text-xs text-gray-500">Pembuat project</p></div>
                </div>
                <div class="flex items-start gap-3 border-t border-gray-200 p-5 dark:border-gray-700 sm:border-l xl:border-t-0">
                    <x-heroicon-o-clock class="mt-0.5 h-5 w-5 shrink-0 text-emerald-500" />
                    <div><p class="text-xs font-bold uppercase tracking-wide text-gray-400">Terakhir Diperbarui</p><p class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $project->updated_at->format('d M Y, H:i') }}</p><p class="mt-1 text-xs text-gray-500">{{ $project->updated_at->diffForHumans() }}</p></div>
                </div>
            </div>
        </section>

        @if($editProjectModalOpen)
            <div class="fixed inset-0 z-[130] flex items-center justify-center p-4">
                <button type="button" wire:click="closeEditProjectModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal edit project"></button>
                <form wire:submit="saveProjectInformation" class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Edit Project</h3>
                            <p class="mt-1 text-sm text-gray-500">Perbarui informasi utama dan periode pelaksanaan project.</p>
                        </div>
                        <button type="button" wire:click="closeEditProjectModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="grid gap-4 overflow-y-auto p-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Nama Project *</label>
                            <input wire:model="editProjectName" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Contoh: Pengembangan Menu Seasonal">
                            @error('editProjectName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Deskripsi Project</label>
                            <textarea wire:model="editProjectDescription" rows="4" class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Jelaskan tujuan, ruang lingkup, dan hasil yang diharapkan..."></textarea>
                            @error('editProjectDescription')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Start Date *</label>
                            <input wire:model="editProjectStartDate" type="date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @error('editProjectStartDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">End Date *</label>
                            <input wire:model="editProjectEndDate" type="date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @error('editProjectEndDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                        <button type="button" wire:click="closeEditProjectModal" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveProjectInformation" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveProjectInformation">Simpan Perubahan</span><span wire:loading wire:target="saveProjectInformation">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Critical Control Point (CCP)</h3>
                    <p class="text-sm text-gray-500">Dokumen pendukung titik kendali kritis untuk project ini.</p>
                </div>
                @if($canManage)
                    <button type="button" wire:click="openCcpUploadModal" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">
                        <x-heroicon-o-document-plus class="h-4 w-4" /> Tambah CCP
                    </button>
                @endif
            </div>

            <div class="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
                @forelse($project->documents as $document)
                    <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-document-text class="h-5 w-5" /></div>
                            <div class="min-w-0 flex-1"><p class="truncate font-bold text-gray-900 dark:text-white">{{ $document->name }}</p><p class="truncate text-xs text-gray-500">{{ $document->original_name }}</p></div>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <a href="{{ $document->downloadUrl() }}" class="flex-1 rounded-lg bg-blue-50 px-3 py-2 text-center text-xs font-bold text-blue-700 hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-300">Download</a>
                            @if($canManage)<button type="button" wire:click="deleteCcpDocument({{ $document->id }})" wire:confirm="Hapus dokumen {{ $document->name }}?" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50">Hapus</button>@endif
                        </div>
                    </article>
                @empty
                    <p class="py-8 text-center text-sm text-gray-500 md:col-span-2 xl:col-span-3">Belum ada dokumen CCP.</p>
                @endforelse
            </div>
        </section>

        @if($ccpUploadModalOpen)
            <div class="fixed inset-0 z-[130] flex items-center justify-center p-4">
                <button type="button" wire:click="closeCcpUploadModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal upload CCP"></button>
                <form wire:submit="saveCcpDocuments" class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Tambah CCP</h3>
                            <p class="mt-1 text-sm text-gray-500">Unggah dokumen pendukung Critical Control Point untuk project ini.</p>
                        </div>
                        <button type="button" wire:click="closeCcpUploadModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="space-y-4 overflow-y-auto p-5">
                        @foreach($ccpDocumentUploads as $documentIndex => $documentUpload)
                            <div wire:key="ccp-document-upload-{{ $documentIndex }}" class="space-y-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">Dokumen CCP {{ $documentIndex + 1 }}</p>
                                    @if(count($ccpDocumentUploads) > 1)
                                        <button type="button" wire:click="removeCcpDocumentUpload({{ $documentIndex }})" class="text-xs font-bold text-red-600 hover:text-red-700">Hapus</button>
                                    @endif
                                </div>
                                <div>
                                    <label class="{{ $label }}">Nama Dokumen *</label>
                                    <input wire:model="ccpDocumentUploads.{{ $documentIndex }}.name" class="{{ $input }}" placeholder="Contoh: CCP Produksi Croissant">
                                    @error("ccpDocumentUploads.$documentIndex.name")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">Attachment *</label>
                                    @if($documentUpload['file'] ?? null)
                                        <div class="mb-2 flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                                            <x-heroicon-o-paper-clip class="h-4 w-4 shrink-0 text-blue-400" />
                                            <span class="min-w-0 flex-1 truncate text-xs text-gray-600 dark:text-gray-300">{{ $documentUpload['file']->getClientOriginalName() }}</span>
                                        </div>
                                    @endif
                                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:hover:border-blue-600 dark:hover:bg-blue-900/20">
                                        <x-heroicon-o-arrow-up-tray class="h-5 w-5 text-gray-400" />
                                        <span class="text-sm text-gray-400">Tambah File / Dokumen CCP</span>
                                        <input wire:model="ccpDocumentUploads.{{ $documentIndex }}.file" type="file" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp">
                                    </label>
                                    <p class="mt-1.5 text-xs text-gray-400">PDF, Office, JPG, PNG, atau WebP · maksimal 20 MB.</p>
                                    <p wire:loading wire:target="ccpDocumentUploads.{{ $documentIndex }}.file" class="mt-1 text-xs text-blue-600">Mengunggah file...</p>
                                    @error("ccpDocumentUploads.$documentIndex.file")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        @endforeach

                        <button type="button" wire:click="addCcpDocumentUpload" class="inline-flex items-center gap-2 text-sm font-bold text-blue-600 hover:text-blue-700">
                            <x-heroicon-o-plus class="h-4 w-4" /> Tambah Dokumen Lain
                        </button>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                        <button type="button" wire:click="closeCcpUploadModal" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveCcpDocuments,ccpDocumentUploads.*.file" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveCcpDocuments">Simpan CCP</span><span wire:loading wire:target="saveCcpDocuments">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif

        @can('view bill of materials')
            @php $materialForecast = $this->materialForecast(); @endphp
            <section class="overflow-hidden rounded-2xl border border-emerald-200 bg-white dark:border-emerald-900/70 dark:bg-gray-900">
                <div class="flex flex-wrap gap-2 border-b border-gray-200 bg-white px-5 py-3 dark:border-gray-700 dark:bg-gray-900" role="group" aria-label="Jenis Material Forecast">
                    <button type="button" wire:click="setForecastType('kitchen')" aria-pressed="{{ $forecastType === 'kitchen' ? 'true' : 'false' }}" @class(['inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold transition', 'bg-emerald-600 text-white' => $forecastType === 'kitchen', 'border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800' => $forecastType !== 'kitchen'])><x-heroicon-o-building-storefront class="h-4 w-4" />Forecast Kitchen</button>
                    <button type="button" wire:click="setForecastType('store')" aria-pressed="{{ $forecastType === 'store' ? 'true' : 'false' }}" @class(['inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold transition', 'bg-blue-600 text-white' => $forecastType === 'store', 'border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800' => $forecastType !== 'store'])><x-heroicon-o-shopping-bag class="h-4 w-4" />Forecast Store</button>
                    <span wire:loading wire:target="setForecastType" role="status" class="self-center text-xs text-gray-500">Menghitung forecast…</span>
                </div>
                <div class="border-b border-emerald-200 bg-emerald-50/70 p-5 dark:border-emerald-900/70 dark:bg-emerald-950/20">
                    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-emerald-200 bg-white text-emerald-600 dark:border-emerald-800 dark:bg-gray-900 dark:text-emerald-300">
                                <x-heroicon-o-calculator class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Purchasing Preparation</p>
                                <h3 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">Material Forecast · {{ $forecastType === 'store' ? 'Store' : 'Kitchen' }}</h3>
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">Perkiraan total bahan baku yang perlu disiapkan Purchasing berdasarkan proyeksi penjualan seluruh produk dalam project dan {{ $forecastType === 'store' ? 'BOM Menu' : 'Main Recipe' }}.</p>
                                <div class="mt-3 inline-flex flex-wrap items-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-gray-900 dark:text-emerald-200">
                                    <x-heroicon-o-variable class="h-4 w-4 shrink-0" />
                                    <span><strong>Rumus:</strong> Sales Projection × Qty {{ $forecastType === 'store' ? 'BOM Menu' : 'Main Recipe' }}{{ $forecastType === 'store' ? '' : ' + Tolerance bahan' }}</span>
                                </div>
                                <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">Component dan WIP ditelusuri sampai bahan baku terakhir. Bahan dengan kode dan unit yang sama otomatis dijumlahkan. {{ $forecastType === 'store' ? 'Jika satu produk memiliki beberapa BOM Menu, setiap BOM Menu dihitung memakai seluruh proyeksi produk tersebut.' : '' }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                            <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-white p-3.5 dark:border-emerald-800 dark:bg-gray-900">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    <x-heroicon-o-cube class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Produk Terhitung</p>
                                    <p class="mt-0.5 text-xl font-bold text-gray-900 dark:text-white">{{ number_format($materialForecast['projected_products']) }}</p>
                                    <p class="text-[10px] text-gray-500">memiliki projection &amp; {{ $forecastType === 'store' ? 'BOM Menu' : 'Main Recipe' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-white p-3.5 dark:border-emerald-800 dark:bg-gray-900">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                    <x-heroicon-o-chart-bar class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Total Proyeksi Penjualan</p>
                                    <p class="mt-0.5 text-xl font-bold text-gray-900 dark:text-white">{{ number_format($materialForecast['projected_units'], 0, ',', '.') }}</p>
                                    <p class="text-[10px] text-gray-500">unit produk dalam project</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($materialForecast['warnings'] !== [])
                    <div class="border-b border-amber-200 bg-amber-50 px-5 py-4 dark:border-amber-900 dark:bg-amber-950/20">
                        <div class="flex items-start gap-3">
                            <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                            <div>
                                <p class="text-sm font-bold text-amber-800 dark:text-amber-200">Mapping WIP Belum Lengkap</p>
                                <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-300">Lengkapi BOM turunan berikut di ESB agar bahan bakunya dapat dihitung.</p>
                                <ul class="mt-3 space-y-2">
                                    @foreach($materialForecast['warnings'] as $warning)
                                        <li class="rounded-lg border border-amber-200 bg-white/70 px-3 py-2 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-gray-900/40 dark:text-amber-200">{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500 dark:bg-gray-800/70">
                            <tr>
                                <th class="px-5 py-3 text-left">Kode</th>
                                <th class="px-5 py-3 text-left">Nama Bahan</th>
                                <th class="px-5 py-3 text-right">Kebutuhan Gross</th>
                                <th class="px-5 py-3 text-left">Unit</th>
                                <th class="px-5 py-3 text-center">Sumber Produk</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($materialForecast['rows'] as $row)
                                <tr>
                                    <td class="px-5 py-3 font-mono text-xs font-bold text-emerald-700">{{ $row['code'] ?: 'MANUAL' }}</td>
                                    <td class="px-5 py-3 font-semibold text-gray-800 dark:text-gray-100">{{ $row['name'] }}</td>
                                    <td class="px-5 py-3 text-right text-base font-bold text-gray-900 dark:text-white">{{ number_format($row['quantity'], 2, ',', '.') }}</td>
                                    <td class="px-5 py-3 font-bold text-gray-500">{{ $row['unit'] }}</td>
                                    <td class="px-5 py-3 text-center text-gray-500">{{ $row['product_count'] }} produk</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">Belum ada forecast. Isi Sales Projection dan {{ $forecastType === 'store' ? 'BOM Menu' : 'Main Recipe' }} pada produk project ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endcan

        <div>
            <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Menu Release</h3>
                        <p class="text-sm text-gray-500">Daftar menu yang dikembangkan dan akan dirilis dalam project ini.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if($canExportKitchenBom)
                            <button type="button" wire:click="openProjectBomExport('kitchen')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm font-bold text-emerald-700 hover:bg-emerald-100">
                                <x-heroicon-o-document-arrow-down class="h-4 w-4" /> Export Kitchen PDF
                            </button>
                        @endif
                        @if($canExportStoreBom)
                            <button type="button" wire:click="openProjectBomExport('store')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100">
                                <x-heroicon-o-document-arrow-down class="h-4 w-4" /> Export Store PDF
                            </button>
                        @endif
                        @if($canManage)
                            <button type="button" wire:click="openCreateProduct" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">
                                <x-heroicon-o-plus class="h-4 w-4" /> Tambah Menu
                            </button>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                    @forelse($project->products as $product)
                        @php
                            $imageUrl = $product->imageUrl();
                            $statusStyle = match($product->status) {
                                'released' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
                                'ready' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
                                'trial' => 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300',
                                'development' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
                                'cancelled' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
                                default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                            };
                            $activePrices = $product->currentRegionalPrices->unique('sales_region_id');
                            $offlineMin = $activePrices->min('offline_price');
                            $offlineMax = $activePrices->max('offline_price');
                            $onlineMin = $activePrices->min('online_price');
                            $onlineMax = $activePrices->max('online_price');
                            $projectionQuantity = $product->salesProjections->sum('target_quantity');
                            $projectionRevenue = $product->salesProjections->sum('target_revenue');
                        @endphp
                        <article class="flex flex-col rounded-xl border border-gray-200 p-4 transition hover:border-blue-300 dark:border-gray-700 dark:hover:border-blue-700">
                            <div class="flex items-start justify-between gap-3">
                                @if($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="h-12 w-12 rounded-lg border border-gray-200 object-cover dark:border-gray-700">
                                @else
                                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                                        <x-heroicon-o-cake class="h-6 w-6" />
                                    </div>
                                @endif
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusStyle }}">{{ \App\Models\RndProjectProduct::STATUSES[$product->status] ?? ucfirst($product->status) }}</span>
                            </div>
                            <p class="mt-3 font-mono text-xs font-bold text-blue-600">{{ $product->product_code ?: 'Belum ada kode' }}</p>
                            <h4 class="mt-1 text-base font-bold text-gray-900 dark:text-white">{{ $product->name }}</h4>
                            <p class="mt-1 line-clamp-2 min-h-10 text-sm leading-5 text-gray-500">{{ $product->description ?: 'Tidak ada deskripsi menu.' }}</p>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <div class="rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60">
                                    <p class="text-[11px] text-gray-400">Harga Offline</p>
                                    <p class="mt-0.5 text-sm font-bold text-gray-800 dark:text-gray-100">
                                        @if($offlineMin === null)Belum diatur
                                        @elseif((float) $offlineMin === (float) $offlineMax)Rp {{ number_format((float) $offlineMin, 0, ',', '.') }}
                                        @else Rp {{ number_format((float) $offlineMin, 0, ',', '.') }}–{{ number_format((float) $offlineMax, 0, ',', '.') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60">
                                    <p class="text-[11px] text-gray-400">Harga Online</p>
                                    <p class="mt-0.5 text-sm font-bold text-gray-800 dark:text-gray-100">
                                        @if($onlineMin === null)Belum diatur
                                        @elseif((float) $onlineMin === (float) $onlineMax)Rp {{ number_format((float) $onlineMin, 0, ',', '.') }}
                                        @else Rp {{ number_format((float) $onlineMin, 0, ',', '.') }}–{{ number_format((float) $onlineMax, 0, ',', '.') }}
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <div class="rounded-lg bg-blue-50 p-2.5 dark:bg-blue-950/30">
                                    <p class="text-[11px] text-blue-500">Shelf Life</p>
                                    <p class="mt-0.5 text-sm font-bold text-blue-800 dark:text-blue-200">
                                        {{ $product->shelf_life_value ? $product->shelf_life_value.' '.(\App\Models\RndProjectProduct::SHELF_LIFE_UNITS[$product->shelf_life_unit] ?? $product->shelf_life_unit) : 'Belum diatur' }}
                                    </p>
                                </div>
                                <div class="rounded-lg bg-emerald-50 p-2.5 dark:bg-emerald-950/30">
                                    <p class="text-[11px] text-emerald-500">Sales Projection</p>
                                    <p class="mt-0.5 text-sm font-bold text-emerald-800 dark:text-emerald-200">{{ number_format((float) $projectionQuantity, 0, ',', '.') }} unit</p>
                                    <p class="text-[10px] text-emerald-600">Rp {{ number_format((float) $projectionRevenue, 0, ',', '.') }}</p>
                                </div>
                            </div>

                            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                                <span>{{ $product->release_date ? 'Rilis '.$product->release_date->format('d M Y') : 'Tanggal rilis belum diatur' }} · {{ $activePrices->count() }} Region</span>
                                <span class="font-bold text-blue-600">{{ $product->boms->count() }} BOM</span>
                            </div>

                            <div class="mt-auto flex items-center gap-2 pt-4">
                                <a href="{{ \App\Filament\Helpdesk\Pages\ViewProjectProductPage::getUrl(['project' => $project->id, 'product' => $product->id]) }}" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">
                                    Buka Menu
                                    <x-heroicon-o-arrow-right class="h-4 w-4" />
                                </a>
                                @if($canManage)
                                    <button type="button" wire:click="editProduct({{ $product->id }})" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800" title="Edit Menu">
                                        <x-heroicon-o-pencil-square class="h-4 w-4" />
                                    </button>
                                    <button type="button" wire:click="deleteProduct({{ $product->id }})" wire:confirm="Hapus menu ini?" class="rounded-lg border border-red-200 p-2 text-red-600 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950/30" title="Hapus Menu">
                                        <x-heroicon-o-trash class="h-4 w-4" />
                                    </button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full rounded-xl border border-dashed border-gray-300 py-14 text-center dark:border-gray-700">
                            <x-heroicon-o-cake class="mx-auto h-11 w-11 text-gray-300" />
                            <h4 class="mt-3 font-bold text-gray-700 dark:text-gray-200">Belum ada Menu Release</h4>
                            <p class="mt-1 text-sm text-gray-500">Tambahkan menu sebelum membuat atau mengimport BOM.</p>
                        </div>
                    @endforelse
                </div>
            </section>

        </div>

        <template x-teleport="body">
            <div x-show="productFormOpen" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-3 sm:p-6">
                <div class="absolute inset-0 bg-slate-950/50" @click="productFormOpen = false"></div>
                <div x-show="productFormOpen" x-transition class="relative max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $editingProductId ? 'Edit Menu Release' : 'Tambah Menu Release' }}</h3>
                            <p class="text-sm text-gray-500">Lengkapi informasi menu dan harga penjualan.</p>
                        </div>
                        <button type="button" @click="productFormOpen = false" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <form wire:submit="saveProduct" class="space-y-4 p-5">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <label class="{{ $label }}">Foto Menu</label>
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 dark:border-gray-600 dark:bg-gray-800">
                                    @if($productPhoto)
                                        <img src="{{ $productPhoto->temporaryUrl() }}" alt="Preview foto menu" class="h-full w-full object-cover">
                                    @elseif($this->productImageUrl())
                                        <img src="{{ $this->productImageUrl() }}" alt="Foto menu" class="h-full w-full object-cover">
                                    @else
                                        <x-heroicon-o-photo class="h-9 w-9 text-gray-300" />
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <input wire:model="productPhoto" type="file" accept="image/jpeg,image/png,image/webp"
                                           class="block w-full rounded-lg border border-gray-300 bg-white text-sm text-gray-600 file:mr-3 file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-bold file:text-blue-700 hover:file:bg-blue-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    <p class="mt-2 text-xs text-gray-500">JPG, PNG, atau WebP. Maksimal 5 MB.</p>
                                    <div wire:loading wire:target="productPhoto" class="mt-2 text-xs font-semibold text-blue-600">Menyiapkan preview foto...</div>
                                    @error('productPhoto')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="{{ $label }}">Nama Menu *</label>
                                <input wire:model="productName" class="{{ $input }}" placeholder="Contoh: Strawberry Croissant">
                                @error('productName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">Kode Menu / SKU</label>
                                <input wire:model="productCode" class="{{ $input }}" placeholder="Contoh: PRD-STB-001">
                                @error('productCode')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">Target Tanggal Rilis</label>
                                <input wire:model="releaseDate" type="date" class="{{ $input }}">
                                @error('releaseDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">Status *</label>
                                <select wire:model="productStatus" class="{{ $input }}">
                                    @foreach(\App\Models\RndProjectProduct::STATUSES as $value => $statusLabel)
                                        <option value="{{ $value }}">{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                                @error('productStatus')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="{{ $label }}">Deskripsi Menu</label>
                                <textarea wire:model="productDescription" rows="4" class="{{ $input }}" placeholder="Deskripsi, positioning, atau catatan pengembangan menu..."></textarea>
                                @error('productDescription')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <section class="rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="border-b border-gray-200 p-4 dark:border-gray-700">
                                <h4 class="font-bold text-gray-900 dark:text-white">Shelf Life & Storage</h4>
                                <p class="text-xs text-gray-500">Informasi ketahanan dan kondisi penyimpanan menu.</p>
                            </div>
                            <div class="grid gap-4 p-4 md:grid-cols-3">
                                <div>
                                    <label class="{{ $label }}">Shelf Life</label>
                                    <input wire:model="shelfLifeValue" type="number" min="1" class="{{ $input }}" placeholder="Contoh: 6">
                                    @error('shelfLifeValue')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">Satuan</label>
                                    <select wire:model="shelfLifeUnit" class="{{ $input }}">
                                        @foreach(\App\Models\RndProjectProduct::SHELF_LIFE_UNITS as $value => $unitLabel)
                                            <option value="{{ $value }}">{{ $unitLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('shelfLifeUnit')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">Kondisi Penyimpanan</label>
                                    <select wire:model="storageCondition" class="{{ $input }}">
                                        @foreach(\App\Models\RndProjectProduct::STORAGE_CONDITIONS as $value => $conditionLabel)
                                            <option value="{{ $value }}">{{ $conditionLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('storageCondition')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="md:col-span-3">
                                    <label class="{{ $label }}">Catatan Penyimpanan</label>
                                    <input wire:model="storageNotes" class="{{ $input }}" placeholder="Contoh: Simpan tertutup pada suhu 2–5°C">
                                    @error('storageNotes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </section>
                        <section class="rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700 sm:flex-row sm:items-center">
                                <div>
                                    <h4 class="font-bold text-gray-900 dark:text-white">Sales Projection</h4>
                                    <p class="text-xs text-gray-500">Target bulanan per region dan channel.</p>
                                </div>
                                <button type="button" wire:click="addSalesProjection" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">+ Tambah Projection</button>
                            </div>
                            <div class="space-y-3 p-4">
                                @forelse($salesProjections as $index => $projection)
                                    <div wire:key="sales-projection-{{ $projection['id'] ?? 'new-'.$index }}" class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                        <input wire:model="salesProjections.{{ $index }}.id" type="hidden">
                                        <div class="grid gap-3 md:grid-cols-3">
                                            <div>
                                                <label class="{{ $label }}">Periode *</label>
                                                <input wire:model="salesProjections.{{ $index }}.projection_month" type="month" class="{{ $input }}">
                                            </div>
                                            <input wire:model="salesProjections.{{ $index }}.sales_region_id" type="hidden">
                                            <div>
                                                <label class="{{ $label }}">Channel *</label>
                                                <select wire:model="salesProjections.{{ $index }}.channel" class="{{ $input }}">
                                                    @foreach(\App\Models\RndProductSalesProjection::CHANNELS as $value => $channelLabel)
                                                        <option value="{{ $value }}">{{ $channelLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="{{ $label }}">Target Revenue *</label>
                                                <input wire:model="salesProjections.{{ $index }}.target_revenue" type="number" min="0" step="1" class="{{ $input }}" placeholder="0">
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="{{ $label }}">Total Target Quantity</label>
                                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-bold text-blue-700 dark:border-gray-700 dark:bg-gray-800 dark:text-blue-300">
                                                    {{ number_format(collect($projection['branch_targets'])->where('enabled', true)->sum(fn (array $target): float => (float) ($target['target_quantity'] ?: 0)), 2, ',', '.') }}
                                                </div>
                                            </div>
                                            <div class="md:col-span-3">
                                                <div class="mb-3">
                                                    <label class="{{ $label }}">Target Quantity per Branch *</label>
                                                    <p class="text-xs text-gray-500">Centang store tempat menu aktif pada projection ini, lalu isi target masing-masing.</p>
                                                </div>
                                                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                                                    @forelse($projection['branch_targets'] as $targetIndex => $branchTarget)
                                                        <div wire:key="projection-{{ $index }}-branch-{{ $branchTarget['branch_id'] }}" class="border-b border-gray-100 p-3 transition last:border-b-0 dark:border-gray-800 {{ $branchTarget['enabled'] ? 'bg-blue-50/60 dark:bg-blue-950/20' : 'bg-white dark:bg-gray-900' }}">
                                                            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(180px,260px)] sm:items-center">
                                                                <label class="flex min-w-0 cursor-pointer items-center gap-3">
                                                                    <input wire:model.live="salesProjections.{{ $index }}.branch_targets.{{ $targetIndex }}.enabled" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                                    <span class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $branchTarget['branch_name'] }}</span>
                                                                </label>
                                                                <div>
                                                                    <div class="flex items-center gap-2">
                                                                        <input
                                                                            wire:model.live.debounce.300ms="salesProjections.{{ $index }}.branch_targets.{{ $targetIndex }}.target_quantity"
                                                                            type="number"
                                                                            min="0.01"
                                                                            step="0.01"
                                                                            placeholder="Masukkan target"
                                                                            class="{{ $input }} disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400 dark:disabled:bg-gray-800"
                                                                            @disabled(! $branchTarget['enabled'])
                                                                            @required($branchTarget['enabled'])
                                                                        >
                                                                    </div>
                                                                    @error("salesProjections.$index.branch_targets.$targetIndex.target_quantity")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @empty
                                                        <p class="p-4 text-sm text-gray-500">Belum ada branch aktif.</p>
                                                    @endforelse
                                                </div>
                                                @error("salesProjections.$index.branch_targets")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="{{ $label }}">Asumsi / Catatan</label>
                                                <input wire:model="salesProjections.{{ $index }}.notes" class="{{ $input }}" placeholder="Dasar perhitungan projection">
                                            </div>
                                            <div class="flex items-end justify-end">
                                                <button type="button" wire:click="removeSalesProjection({{ $index }})" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 dark:border-red-900">Hapus</button>
                                            </div>
                                        </div>
                                        @error("salesProjections.$index.projection_month")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                        @error("salesProjections.$index.sales_region_id")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                        @error("salesProjections.$index.target_quantity")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                        @error("salesProjections.$index.target_revenue")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                @empty
                                    <p class="py-6 text-center text-sm text-gray-500">Belum ada projection. Wajib diisi sebelum status Ready/Released.</p>
                                @endforelse
                                @error('salesProjections')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </section>
                        <section class="rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="flex flex-col justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700 sm:flex-row sm:items-center">
                                <div>
                                    <h4 class="font-bold text-gray-900 dark:text-white">Regional Pricing</h4>
                                    <p class="text-xs text-gray-500">Harga online dan offline dapat berbeda untuk setiap region.</p>
                                </div>
                                <div class="w-full sm:w-48">
                                    <label class="{{ $label }}">Berlaku Mulai *</label>
                                    <input wire:model="priceEffectiveFrom" type="date" class="{{ $input }}">
                                    @error('priceEffectiveFrom')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($regionalPrices as $index => $price)
                                    <div class="grid gap-4 p-4 md:grid-cols-[minmax(160px,0.65fr)_minmax(0,2.35fr)] md:items-start">
                                        <div>
                                            <label class="flex cursor-pointer items-start gap-3">
                                                <input wire:model.live="regionalPrices.{{ $index }}.enabled" type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <span>
                                                    <span class="block text-sm font-bold text-gray-900 dark:text-white">{{ $price['region_name'] }}</span>
                                                    <span class="block font-mono text-xs text-gray-400">{{ $price['region_code'] }}</span>
                                                </span>
                                            </label>
                                            <input wire:model="regionalPrices.{{ $index }}.region_id" type="hidden">
                                            <input wire:model="regionalPrices.{{ $index }}.offline_price" type="hidden">
                                            <input wire:model="regionalPrices.{{ $index }}.online_price" type="hidden">
                                        </div>
                                        @if($price['enabled'] ?? false)
                                        <div class="space-y-4">
                                            <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-3 dark:border-gray-700 dark:bg-gray-800/50">
                                                <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-500">Offline</p>
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <div>
                                                        <label class="{{ $label }}">Dine In *</label>
                                                        <input wire:model="regionalPrices.{{ $index }}.dine_in_price" type="number" min="0" step="1" class="{{ $input }}" placeholder="0">
                                                        @error("regionalPrices.$index.dine_in_price")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                                    </div>
                                                    <div>
                                                        <label class="{{ $label }}">Takeaway *</label>
                                                        <input wire:model="regionalPrices.{{ $index }}.takeaway_price" type="number" min="0" step="1" class="{{ $input }}" placeholder="0">
                                                        @error("regionalPrices.$index.takeaway_price")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-3 dark:border-gray-700 dark:bg-gray-800/50">
                                                <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-500">Online</p>
                                                <div class="grid gap-3 sm:grid-cols-3">
                                                    @foreach(['gofood_price' => 'GoFood', 'grabfood_price' => 'GrabFood', 'shopeefood_price' => 'ShopeeFood'] as $field => $channel)
                                                        <div>
                                                            <label class="{{ $label }}">{{ $channel }} *</label>
                                                            <input wire:model="regionalPrices.{{ $index }}.{{ $field }}" type="number" min="0" step="1" class="{{ $input }}" placeholder="0">
                                                            @error("regionalPrices.$index.$field")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                        @else
                                            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700">
                                                Centang region untuk mengisi dan menampilkan harga di PDF.
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="p-5 text-center text-sm text-gray-500">Belum ada region aktif. Tambahkan melalui Master Region Penjualan.</p>
                                @endforelse
                            </div>
                            @error('regionalPrices')<p class="px-4 pb-4 text-xs text-red-600">{{ $message }}</p>@enderror
                        </section>
                        <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <button type="button" @click="productFormOpen = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">Batal</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveProduct" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="saveProduct">{{ $editingProductId ? 'Simpan Perubahan' : 'Tambah Menu' }}</span>
                                <span wire:loading wire:target="saveProduct">Menyimpan...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        @if($projectExportPinModalOpen)
            <div class="fixed inset-0 z-[130] flex items-center justify-center p-4">
                <button type="button" aria-label="Tutup modal" class="absolute inset-0 bg-slate-950/55" wire:click="closeProjectBomExport"></button>
                <div @class([
                    'relative flex w-full flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-900',
                    'h-auto max-h-[calc(100dvh-2rem)] max-w-sm' => $projectExportScope === 'store',
                    'h-[calc(100dvh-2rem)] max-h-[42rem] max-w-md' => $projectExportScope !== 'store',
                ])>
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"><x-heroicon-o-lock-closed class="h-7 w-7" /></div>
                    <h3 class="mt-4 text-xl font-bold text-gray-900 dark:text-white">Export {{ ucfirst($projectExportScope) }} Project</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-500">Semua product dengan BOM {{ ucfirst($projectExportScope) }} akan digabung dalam satu dokumen PDF.</p>
                    <form wire:submit="exportProjectBomPdf" class="mt-5 flex min-h-0 flex-1 flex-col">
                        @if($projectExportScope === 'store')
                            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
                                Seluruh BOM Menu dari semua product dalam project akan otomatis diekspor.
                            </div>
                        @else
                        <div class="mb-4 min-h-0 flex-1 touch-pan-y space-y-3 overflow-y-auto overscroll-contain rounded-xl border border-gray-200 p-3 text-left dark:border-gray-700">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Pilih BOM yang ditampilkan</p>
                            @foreach($record->products as $exportProduct)
                                @php
                                    $productBoms = $exportProduct->boms->filter(fn ($bom) => $projectExportScope === 'store' ? $bom->pivot->usage_type === 'menu' : $bom->pivot->usage_type !== 'menu');
                                    $productMainBoms = $productBoms->filter(fn ($bom) => $bom->pivot->usage_type === 'main');
                                    $productRootBoms = $productBoms->filter(fn ($bom) => $bom->pivot->usage_type === 'menu' || ! $bom->pivot->parent_rnd_project_bom_id);
                                    $orderedProductBoms = $productMainBoms->flatMap(fn ($mainBom) => collect([$mainBom])->concat(
                                        $productBoms->filter(fn ($bom) => (int) $bom->pivot->parent_rnd_project_bom_id === $mainBom->id)
                                    ))->concat($productRootBoms)->unique('id')->values();
                                @endphp
                                @if($orderedProductBoms->isNotEmpty())
                                    <div class="space-y-2">
                                        <p class="px-1 text-xs font-bold text-gray-700 dark:text-gray-200">{{ $exportProduct->name }}</p>
                                        @foreach($orderedProductBoms as $exportBom)
                                            @php
                                                $isChildBom = filled($exportBom->pivot->parent_rnd_project_bom_id);
                                                $typeLabel = match($exportBom->pivot->usage_type) {
                                                    'main' => 'Main Recipe',
                                                    'component' => 'Component',
                                                    'packaging' => 'Packaging',
                                                    'menu' => 'Menu',
                                                    default => ucfirst($exportBom->pivot->usage_type),
                                                };
                                            @endphp
                                            <div class="rounded-lg border border-gray-100 p-2 dark:border-gray-800 {{ $isChildBom ? 'ml-5 border-l-2 border-l-blue-300' : '' }}">
                                                <label class="flex cursor-pointer items-start gap-3 rounded-lg px-1 py-1 hover:bg-gray-50 dark:hover:bg-gray-800">
                                                    <input wire:model.live="projectExportBomIds" type="checkbox" value="{{ $exportBom->id }}" class="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $exportBom->bom_name }}</span>
                                                        <span class="mt-0.5 inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide {{ $isChildBom ? 'text-blue-600' : 'text-emerald-600' }}">@if($isChildBom)<span aria-hidden="true">↳</span>@endif {{ $typeLabel }}</span>
                                                    </span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        @error('projectExportBomIds')<p class="mb-3 text-sm font-medium text-red-600">Pilih minimal satu BOM.</p>@enderror
                        @endif
                        <input wire:model="projectExportPin" type="password" inputmode="numeric" autocomplete="one-time-code" placeholder="Masukkan PIN" class="w-full rounded-xl border border-gray-300 px-4 py-3 text-center text-lg font-bold tracking-[0.3em] dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('projectExportPin')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button type="button" wire:click="closeProjectBomExport" class="rounded-xl border border-gray-300 px-4 py-3 text-sm font-bold text-gray-700 dark:border-gray-600 dark:text-gray-200">Batal</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="exportProjectBomPdf" class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="exportProjectBomPdf">Download PDF</span><span wire:loading wire:target="exportProjectBomPdf">Menyiapkan...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
