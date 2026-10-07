@if($applyTemplateModalOpen)
    @php
        $applySteps = [1 => 'Pilih Template', 2 => 'Atur Template', 3 => 'Preview'];
        $selectedApplyRowCount = count($this->selectedApplyRows());
        $releaseDate = $this->project->end_date;
        $branchNames = $this->taskFilterBranches()->pluck('name', 'id');
        $applyFieldLabel = 'mb-1 block text-[11px] font-semibold text-gray-500 dark:text-gray-400';
        $applyFieldInput = 'h-10 w-full rounded-lg border border-gray-300 bg-white px-2.5 text-sm disabled:cursor-not-allowed disabled:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
    @endphp
    <div class="fixed inset-0 z-[130] flex items-center justify-center p-4" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeApplyTemplateModal()">
        <button type="button" wire:click="closeApplyTemplateModal" class="absolute inset-0 bg-gray-950/60" aria-label="Tutup modal Gunakan Template"></button>
        <div class="relative flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true" aria-labelledby="apply-template-heading">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                <div class="min-w-0">
                    <h3 id="apply-template-heading" class="text-lg font-bold text-gray-900 dark:text-white">Gunakan Template</h3>
                    <ol class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs" aria-label="Langkah">
                        @foreach($applySteps as $stepNumber => $stepLabel)
                            <li @class(['font-bold text-blue-700 dark:text-blue-300' => $applyTemplateStep === $stepNumber, 'text-gray-400' => $applyTemplateStep !== $stepNumber]) @if($applyTemplateStep === $stepNumber) aria-current="step" @endif>{{ $stepNumber }}. {{ $stepLabel }}</li>
                        @endforeach
                    </ol>
                </div>
                <button type="button" wire:click="closeApplyTemplateModal" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Tutup">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            <div class="space-y-4 overflow-y-auto p-5">
                @error('applyTemplate')
                    <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300" role="alert">{{ $message }}</p>
                @enderror

                @if($applyTemplateStep === 1)
                    @php $applicableTemplates = $this->applicableTemplates(); @endphp
                    @if($applicableTemplates->isEmpty())
                        <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center dark:border-gray-700">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Belum ada template aktif yang memiliki checkpoint.</p>
                            @can('viewAny', \App\Models\RndProjectTaskTemplate::class)
                                <a href="{{ \App\Filament\Helpdesk\Resources\RndProjectTaskTemplates\RndProjectTaskTemplateResource::getUrl('index') }}" class="mt-2 inline-flex text-sm font-bold text-blue-600 hover:underline dark:text-blue-400">Kelola Template Checkpoint</a>
                            @endcan
                        </div>
                    @else
                        <div>
                            <label for="apply-template-id" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">Template *</label>
                            <select id="apply-template-id" wire:model="applyTemplateId" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <option value="">Pilih template...</option>
                                @foreach($applicableTemplates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->checkpoints_count }} checkpoint)</option>
                                @endforeach
                            </select>
                            @error('applyTemplateId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">Branch &amp; PIC mengikuti template. Tanggal assign dan deadline diatur pada langkah berikutnya, maksimal tanggal rilis Project ({{ $releaseDate->format('d M Y') }}).</p>
                    @endif
                @elseif($applyTemplateStep === 2)
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm text-gray-600 dark:text-gray-300">Isi tanggal setiap checkpoint, maksimal tanggal rilis <span class="font-semibold">{{ $releaseDate->format('d M Y') }}</span>. Hilangkan centang untuk checkpoint yang tidak diperlukan.</p>
                        <x-filament::badge color="info">{{ $selectedApplyRowCount }} dari {{ count($applyRows) }} dipilih</x-filament::badge>
                    </div>
                    @error('applyRows')<p class="text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
                    <div class="space-y-3">
                        @foreach($applyRows as $index => $row)
                            @php
                                $branchRowsHaveErrors = collect($errors->keys())->contains(fn (string $key): bool => str_starts_with($key, "applyRows.{$index}.branch_rows"));
                                $branchRowsIncomplete = collect($row['branch_rows'])->contains(fn (array $branchRow): bool => blank($branchRow['branch_id']) || $branchRow['user_ids'] === []);
                            @endphp
                            <div wire:key="apply-row-{{ $row['checkpoint_id'] }}" x-data="{ editingBranches: @js($branchRowsHaveErrors || $branchRowsIncomplete) }" @class(['rounded-xl border p-3', 'border-gray-200 dark:border-gray-700' => $row['selected'], 'border-dashed border-gray-200 opacity-60 dark:border-gray-700' => ! $row['selected']])>
                                <div class="grid gap-2 sm:grid-cols-[auto_minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] sm:items-start">
                                    <div>
                                        <label for="apply-row-{{ $index }}-selected" class="{{ $applyFieldLabel }}">#{{ $index + 1 }}</label>
                                        <div class="flex h-10 items-center justify-center">
                                            <input id="apply-row-{{ $index }}-selected" type="checkbox" wire:model.live="applyRows.{{ $index }}.selected" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" aria-label="Sertakan checkpoint {{ $index + 1 }}">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="apply-row-{{ $index }}-title" class="{{ $applyFieldLabel }}">Nama Task</label>
                                        <input id="apply-row-{{ $index }}-title" wire:model="applyRows.{{ $index }}.title" @disabled(! $row['selected']) class="{{ $applyFieldInput }}">
                                        @error("applyRows.$index.title")<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="apply-row-{{ $index }}-priority" class="{{ $applyFieldLabel }}">Prioritas</label>
                                        <select id="apply-row-{{ $index }}-priority" wire:model="applyRows.{{ $index }}.priority" @disabled(! $row['selected']) class="{{ $applyFieldInput }}">
                                            @foreach($this->taskPriorityOptions() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="apply-row-{{ $index }}-assigned" class="{{ $applyFieldLabel }}">Assign</label>
                                        <input id="apply-row-{{ $index }}-assigned" type="date" max="{{ $releaseDate->toDateString() }}" wire:model="applyRows.{{ $index }}.assigned_date" @disabled(! $row['selected']) class="{{ $applyFieldInput }}">
                                        @error("applyRows.$index.assigned_date")<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="apply-row-{{ $index }}-due" class="{{ $applyFieldLabel }}">Deadline</label>
                                        <input id="apply-row-{{ $index }}-due" type="date" max="{{ $releaseDate->toDateString() }}" wire:model="applyRows.{{ $index }}.due_date" @disabled(! $row['selected']) class="{{ $applyFieldInput }}">
                                        @error("applyRows.$index.due_date")<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                @if($row['selected'])
                                    <div class="mt-3 flex flex-wrap items-start justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-gray-800/50">
                                        <div class="min-w-0 space-y-0.5">
                                            @foreach($row['branch_rows'] as $branchRow)
                                                @if(filled($branchRow['branch_id']))
                                                    <p class="text-gray-700 dark:text-gray-200">
                                                        <span class="font-semibold">{{ $branchNames[(int) $branchRow['branch_id']] ?? '-' }}:</span>
                                                        @if($branchRow['user_ids'] === [])
                                                            <span class="text-amber-700 dark:text-amber-300">PIC belum dipilih</span>
                                                        @else
                                                            {{ collect($branchRow['user_ids'])->map(fn ($userId) => $this->eligiblePicsForBranch($branchRow['branch_id'])[(int) $userId] ?? 'PIC tidak valid')->join(', ') }}
                                                        @endif
                                                    </p>
                                                @else
                                                    <p class="text-amber-700 dark:text-amber-300">Branch &amp; PIC belum diatur di template.</p>
                                                @endif
                                            @endforeach
                                        </div>
                                        <button type="button" x-on:click="editingBranches = ! editingBranches" x-text="editingBranches ? 'Tutup' : 'Ubah Branch & PIC'" class="shrink-0 font-bold text-blue-600 hover:underline dark:text-blue-400">Ubah Branch &amp; PIC</button>
                                    </div>
                                    @error("applyRows.$index.branch_rows")<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                                    <div x-show="editingBranches" x-cloak class="mt-3">
                                        @include('filament.helpdesk.rnd-projects.partials.task-branch-rows', ['rowsProperty' => "applyRows.{$index}.branch_rows", 'rows' => $row['branch_rows']])
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    @php $previewSlides = $this->applyPreviewSlides(); @endphp
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-3.5 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200">
                        <p class="font-bold">{{ $selectedApplyRowCount }} Task akan dibuat di Project “{{ $this->project->name }}”.</p>
                        <p class="mt-1 text-xs">Geser antar checkpoint untuk memeriksa. Setiap PIC menerima notifikasi assignment setelah Task tersimpan.</p>
                    </div>

                    <div
                        wire:key="apply-preview-{{ $applyTemplateId }}-{{ count($previewSlides) }}"
                        x-data="{ current: 0, total: {{ count($previewSlides) }}, go(index) { this.current = Math.max(0, Math.min(this.total - 1, index)) } }"
                        x-on:keydown.arrow-left.prevent="go(current - 1)"
                        x-on:keydown.arrow-right.prevent="go(current + 1)"
                        tabindex="0"
                        role="region"
                        aria-roledescription="carousel"
                        aria-label="Preview checkpoint"
                        class="rounded-2xl border border-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-gray-700"
                    >
                        <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                            <button type="button" x-on:click="go(current - 1)" x-bind:disabled="current === 0" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Checkpoint sebelumnya">
                                <x-heroicon-o-chevron-left class="h-4 w-4" />
                            </button>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200" aria-live="polite">Checkpoint <span x-text="current + 1">1</span> dari {{ count($previewSlides) }}</p>
                            <button type="button" x-on:click="go(current + 1)" x-bind:disabled="current === total - 1" class="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Checkpoint berikutnya">
                                <x-heroicon-o-chevron-right class="h-4 w-4" />
                            </button>
                        </div>

                        @foreach($previewSlides as $slideIndex => $slide)
                            <div x-show="current === {{ $slideIndex }}" @if($slideIndex > 0) x-cloak @endif role="group" aria-roledescription="slide" aria-label="{{ $slideIndex + 1 }} dari {{ count($previewSlides) }}" class="space-y-4 p-5">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Checkpoint #{{ $slide['number'] }}</p>
                                    <h4 class="mt-1 text-lg font-bold text-gray-900 dark:text-white">{{ $slide['title'] }}</h4>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <x-filament::badge color="gray">{{ $slide['category'] }}</x-filament::badge>
                                        <x-filament::badge color="info">Prioritas {{ $slide['priority'] }}</x-filament::badge>
                                    </div>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Tanggal Assign</p>
                                        <p class="mt-1 font-semibold text-gray-800 dark:text-gray-100">{{ $slide['assigned_date'] }}</p>
                                    </div>
                                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Deadline</p>
                                        <p class="mt-1 font-semibold text-gray-800 dark:text-gray-100">{{ $slide['due_date'] }}</p>
                                    </div>
                                </div>
                                <div>
                                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Branch &amp; PIC</p>
                                    <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 text-sm dark:divide-gray-800 dark:border-gray-700">
                                        @foreach($slide['branches'] as $branch)
                                            <li class="flex flex-wrap justify-between gap-2 px-3 py-2">
                                                <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $branch['name'] }}</span>
                                                <span class="text-gray-600 dark:text-gray-300">{{ implode(', ', $branch['pics']) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                                @if(filled($slide['description']))
                                    <div class="rounded-xl bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-800/50 dark:text-gray-200">
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Instruksi</p>
                                        <p class="mt-1 leading-6">{{ $slide['description'] }}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        <div class="flex flex-wrap justify-center gap-1.5 border-t border-gray-200 px-4 py-3 dark:border-gray-700" role="tablist" aria-label="Pilih checkpoint">
                            @foreach($previewSlides as $slideIndex => $slide)
                                <button
                                    type="button"
                                    role="tab"
                                    x-on:click="go({{ $slideIndex }})"
                                    x-bind:aria-selected="current === {{ $slideIndex }}"
                                    x-bind:class="current === {{ $slideIndex }} ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800'"
                                    class="h-8 min-w-8 rounded-lg border px-2 text-xs font-bold"
                                    title="{{ $slide['title'] }}"
                                >{{ $slide['number'] }}</button>
                            @endforeach
                        </div>
                    </div>

                    @if($this->applyTemplateWasAppliedBefore())
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200" role="alert">
                            <p class="font-bold">Template ini sudah pernah diterapkan pada Project ini.</p>
                            <label class="mt-2 flex items-center gap-2 text-xs font-semibold">
                                <input type="checkbox" wire:model="applyConfirmDuplicate" class="rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                                Saya mengerti dan tetap ingin membuat Task baru dari template ini.
                            </label>
                            @error('applyConfirmDuplicate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endif
                @endif
            </div>

            <div class="flex flex-wrap justify-between gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                <div>
                    @if($applyTemplateStep > 1)
                        <button type="button" wire:click="backToApplyStep({{ $applyTemplateStep - 1 }})" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Kembali</button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="closeApplyTemplateModal" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Batal</button>
                    @if($applyTemplateStep === 1)
                        <button type="button" wire:click="selectApplyTemplate" wire:loading.attr="disabled" wire:target="selectApplyTemplate" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="selectApplyTemplate">Lanjut Atur Template</span>
                            <span wire:loading wire:target="selectApplyTemplate">Memuat...</span>
                        </button>
                    @elseif($applyTemplateStep === 2)
                        <button type="button" wire:click="continueToApplyPreview" wire:loading.attr="disabled" wire:target="continueToApplyPreview" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="continueToApplyPreview">Lihat Preview</span>
                            <span wire:loading wire:target="continueToApplyPreview">Memeriksa...</span>
                        </button>
                    @else
                        <button type="button" wire:click="applySelectedTemplate" wire:loading.attr="disabled" wire:target="applySelectedTemplate" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="applySelectedTemplate">Buat {{ $selectedApplyRowCount }} Task</span>
                            <span wire:loading wire:target="applySelectedTemplate">Menyimpan...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
