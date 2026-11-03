@php
    use App\Enums\MarketingDesignBundleStatus;
    use App\Enums\MarketingDesignFileRole;
    use App\Filament\Helpdesk\Resources\MarketingDesignBundles\MarketingDesignBundleResource;
@endphp

<x-filament-panels::page>
    @php
        // Actions rendered by hand do not honour visible() on their own; the server still refuses hidden ones.
        $shown = fn (\Filament\Actions\Action $action): ?\Filament\Actions\Action => $action->isVisible() ? $action : null;
        $badge = fn (string $color): string => match ($color) {
            'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
            'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
            'info', 'primary' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
            default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        };
        $badgeBase = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold';
        $card = 'overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900';
        $cardHeader = 'flex flex-col justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-700 sm:flex-row sm:items-center';
        $iconBlock = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl';
        $sectionTitle = 'text-base font-bold text-gray-900 dark:text-white';
        $sectionHint = 'mt-0.5 text-sm text-gray-500 dark:text-gray-400';
        $metaLabel = 'text-xs font-bold uppercase tracking-wide text-gray-400';
        $metaValue = 'mt-1 text-sm font-semibold text-gray-900 break-words dark:text-white';
        $emptyState = 'rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400';
        $formatTime = fn ($value): string => $value?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? '—';
    @endphp

    <div class="space-y-6">
        {{-- Summary --}}
        <section class="{{ $card }}" data-design-card="summary">
            <div class="border-b border-gray-200 p-5 dark:border-gray-700 sm:p-6">
                <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-start">
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                            <x-heroicon-o-swatch class="h-6 w-6" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ MarketingDesignBundleResource::getUrl('index') }}" class="text-xs font-bold uppercase tracking-wider text-blue-600 hover:text-blue-700 dark:text-blue-400">Master Design</a>
                                <span class="text-gray-300 dark:text-gray-600">/</span>
                                <span class="{{ $badgeBase }} {{ $badge($bundle->status->getColor()) }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $bundle->status->getLabel() }}</span>
                                @if($version)
                                    <span class="{{ $badgeBase }} {{ $badge($version->status->getColor()) }}">{{ $version->label() }} · {{ $version->status->getLabel() }}</span>
                                @endif
                                <span class="{{ $badgeBase }} {{ $badge('gray') }}">{{ $bundle->category->getLabel() }}</span>
                            </div>
                            <h2 class="mt-2 break-words text-2xl font-bold text-gray-950 dark:text-white sm:text-3xl">{{ $bundle->name }}</h2>
                            <p class="mt-1 break-words text-sm font-semibold text-gray-600 dark:text-gray-300">{{ $bundle->brand->name }} · {{ $bundle->code }}</p>
                            <p class="mt-2 max-w-3xl break-words text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $bundle->description ?: 'Deskripsi bundle belum ditambahkan.' }}</p>
                            @if($bundle->status === MarketingDesignBundleStatus::Archived)
                                <p class="mt-2 break-words text-sm font-semibold text-amber-700 dark:text-amber-300">Diarsipkan: {{ $bundle->archive_reason }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        {{ $shown($this->editBundleAction) }}
                        {{ $shown($this->createRevisionAction) }}
                        {{ $shown($this->archiveBundleAction) }}
                        {{ $shown($this->restoreBundleAction) }}
                    </div>
                </div>
            </div>
            <div class="grid divide-y divide-gray-200 dark:divide-gray-700 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div class="flex items-start gap-3 p-5">
                    <x-heroicon-o-user-circle class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                    <div class="min-w-0"><p class="{{ $metaLabel }}">Dibuat Oleh</p><p class="{{ $metaValue }}">{{ $bundle->creator?->name ?? '—' }}</p></div>
                </div>
                <div class="flex items-start gap-3 p-5">
                    <x-heroicon-o-rocket-launch class="mt-0.5 h-5 w-5 shrink-0 text-emerald-500" />
                    <div class="min-w-0"><p class="{{ $metaLabel }}">Dipublikasikan Oleh</p><p class="{{ $metaValue }}">{{ $version?->publisher?->name ?? '—' }}</p></div>
                </div>
                <div class="flex items-start gap-3 p-5">
                    <x-heroicon-o-clock class="mt-0.5 h-5 w-5 shrink-0 text-violet-500" />
                    <div class="min-w-0"><p class="{{ $metaLabel }}">Terakhir Diperbarui</p><p class="{{ $metaValue }}">{{ $formatTime($bundle->updated_at) }}</p></div>
                </div>
            </div>
        </section>

        {{-- Informasi Bundle --}}
        <section class="{{ $card }}" data-design-card="bundle">
            <div class="{{ $cardHeader }}">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="{{ $iconBlock }} bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300"><x-heroicon-o-information-circle class="h-5 w-5" /></div>
                    <div class="min-w-0"><h3 class="{{ $sectionTitle }}">Informasi Bundle</h3><p class="{{ $sectionHint }}">Identitas bundle dan Brand pemiliknya.</p></div>
                </div>
            </div>
            <dl class="grid gap-5 p-5 sm:grid-cols-2 xl:grid-cols-4">
                <div class="min-w-0"><dt class="{{ $metaLabel }}">Brand</dt><dd class="{{ $metaValue }}">{{ $bundle->brand->name }}</dd></div>
                <div class="min-w-0"><dt class="{{ $metaLabel }}">Kode Bundle</dt><dd class="{{ $metaValue }}">{{ $bundle->code }}</dd></div>
                <div class="min-w-0"><dt class="{{ $metaLabel }}">Kategori</dt><dd class="{{ $metaValue }}">{{ $bundle->category->getLabel() }}</dd></div>
                <div class="min-w-0"><dt class="{{ $metaLabel }}">Status</dt><dd class="{{ $metaValue }}">{{ $bundle->status->getLabel() }}</dd></div>
            </dl>
        </section>

        {{-- Current Version --}}
        <section class="{{ $card }}" data-design-card="version">
            <div class="{{ $cardHeader }}">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="{{ $iconBlock }} bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-document-check class="h-5 w-5" /></div>
                    <div class="min-w-0"><h3 class="{{ $sectionTitle }}">Current Version</h3><p class="{{ $sectionHint }}">{{ $isInternal ? 'Pilih revisi yang ingin dilihat.' : 'Version published yang berlaku.' }}</p></div>
                </div>
                @if($isInternal && $history->count() > 1)
                    <div class="flex items-center gap-2">
                        <label for="design-version-select" class="text-sm font-semibold text-gray-700 dark:text-gray-200">Revisi</label>
                        <select id="design-version-select" wire:model.live="selectedVersionId" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @foreach($history as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $version?->id)>{{ $option->label() }} · {{ $option->status->getLabel() }}</option>
                            @endforeach
                        </select>
                        <x-filament::loading-indicator wire:loading wire:target="selectedVersionId,selectVersion" class="h-5 w-5 text-gray-400" />
                    </div>
                @endif
            </div>
            <div class="p-5">
                @if($version === null)
                    <p class="{{ $emptyState }}">Bundle ini belum mempunyai version.</p>
                @else
                    <dl class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="min-w-0"><dt class="{{ $metaLabel }}">Version</dt><dd class="{{ $metaValue }}">{{ $version->label() }}</dd></div>
                        <div class="min-w-0"><dt class="{{ $metaLabel }}">Status</dt><dd class="mt-1"><span class="{{ $badgeBase }} {{ $badge($version->status->getColor()) }}">{{ $version->status->getLabel() }}</span></dd></div>
                        <div class="min-w-0"><dt class="{{ $metaLabel }}">Reviewer</dt><dd class="{{ $metaValue }}">{{ $version->reviewer?->name ?? '—' }}</dd><dd class="text-xs text-gray-500">{{ $formatTime($version->reviewed_at) }}</dd></div>
                        <div class="min-w-0"><dt class="{{ $metaLabel }}">Publisher</dt><dd class="{{ $metaValue }}">{{ $version->publisher?->name ?? '—' }}</dd><dd class="text-xs text-gray-500">{{ $formatTime($version->published_at) }}</dd></div>
                        <div class="min-w-0 sm:col-span-2"><dt class="{{ $metaLabel }}">Ringkasan Perubahan</dt><dd class="{{ $metaValue }} whitespace-pre-line font-normal">{{ $version->change_summary ?: '—' }}</dd></div>
                        @if($isInternal)
                            <div class="min-w-0"><dt class="{{ $metaLabel }}">Catatan Review</dt><dd class="{{ $metaValue }} whitespace-pre-line font-normal">{{ $version->review_note ?: '—' }}</dd></div>
                        @endif
                        <div class="min-w-0"><dt class="{{ $metaLabel }}">Catatan Publish</dt><dd class="{{ $metaValue }} whitespace-pre-line font-normal">{{ $version->publish_note ?: '—' }}</dd></div>
                    </dl>

                    @if($version->status->isImmutable())
                        <p class="mt-4 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">Version ini read-only. Perubahan dilakukan lewat revisi baru.</p>
                    @endif

                    @if($versionIssues !== [])
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                            <p class="font-bold">Belum memenuhi syarat review dan publish:</p>
                            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                                @foreach($versionIssues as $issue)<li class="break-words">{{ $issue }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    @if($comparison)
                        <div class="mt-4 rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-700" data-design-comparison>
                            <p class="font-bold text-gray-900 dark:text-white">Perbandingan metadata dengan {{ $comparison['base'] }}</p>
                            @if($comparison['added'] === [] && $comparison['removed'] === [] && $comparison['changed'] === [])
                                <p class="mt-1 text-gray-500 dark:text-gray-400">Belum ada perubahan metadata.</p>
                            @else
                                <ul class="mt-1 space-y-0.5 text-gray-600 dark:text-gray-300">
                                    @foreach($comparison['added'] as $title)<li class="break-words">Item baru: {{ $title }}</li>@endforeach
                                    @foreach($comparison['removed'] as $title)<li class="break-words">Item dilepas: {{ $title }}</li>@endforeach
                                    @foreach($comparison['changed'] as $change)<li class="break-words">{{ $change['title'] }} berubah pada: {{ implode(', ', $change['fields']) }}</li>@endforeach
                                </ul>
                            @endif
                        </div>
                    @endif

                    <div class="mt-5 flex flex-wrap gap-2 [&:not(:has(*))]:hidden">
                        {{ $shown($this->editVersionSummaryAction) }}
                        {{ $shown($this->submitVersionAction) }}
                        {{ $shown($this->reopenVersionAction) }}
                        {{ $shown($this->approveVersionAction) }}
                        {{ $shown($this->requestRevisionAction) }}
                        {{ $shown($this->publishVersionAction) }}
                    </div>
                @endif
            </div>
        </section>

        {{-- Design Items --}}
        <section class="{{ $card }}" data-design-card="items">
            <div class="{{ $cardHeader }}">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="{{ $iconBlock }} bg-violet-50 text-violet-600 dark:bg-violet-950/40 dark:text-violet-300"><x-heroicon-o-squares-2x2 class="h-5 w-5" /></div>
                    <div class="min-w-0"><h3 class="{{ $sectionTitle }}">Design Items</h3><p class="{{ $sectionHint }}">{{ $items->count() }} item pada version ini.</p></div>
                </div>
                <div class="shrink-0">{{ $shown($this->addItemAction) }}</div>
            </div>
            <div class="space-y-3 p-5">
                @forelse($items as $item)
                    @php
                        $itemIssues = $readiness->itemIssues($item);
                        $previewFile = $item->files->firstWhere('role', MarketingDesignFileRole::Preview);
                    @endphp
                    <article wire:key="design-item-{{ $item->id }}" class="flex flex-col gap-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700 sm:flex-row">
                        <div class="h-28 w-full shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800 sm:w-36">
                            @if($previewFile?->isImage())
                                <div x-data="{ failed: false }" class="h-full w-full">
                                    <img x-show="! failed" x-on:error="failed = true" src="{{ route('helpdesk.marketing-design-files.preview', $previewFile) }}" loading="lazy" alt="Preview {{ $item->title }}" class="h-full w-full object-contain">
                                    <div x-show="failed" x-cloak class="flex h-full flex-col items-center justify-center gap-1 p-2 text-center text-xs text-gray-500"><x-heroicon-o-exclamation-triangle class="h-5 w-5" />Preview tidak tersedia</div>
                                </div>
                            @elseif($previewFile)
                                <a href="{{ route('helpdesk.marketing-design-files.preview', $previewFile) }}" target="_blank" rel="noopener" class="flex h-full flex-col items-center justify-center gap-1 p-2 text-center text-xs font-semibold text-blue-600 dark:text-blue-400"><x-heroicon-o-document-text class="h-6 w-6" />Buka preview PDF</a>
                            @else
                                <div class="flex h-full flex-col items-center justify-center gap-1 p-2 text-center text-xs text-gray-400"><x-heroicon-o-photo class="h-6 w-6" />Belum ada preview</div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="{{ $badgeBase }} {{ $badge('gray') }}">{{ $item->typeLabel() }}</span>
                                <span class="{{ $badgeBase }} {{ $badge($item->isPhysical() ? 'warning' : 'info') }}">{{ $item->isPhysical() ? 'Fisik' : 'Digital' }}</span>
                                @if($isInternal)
                                    <span class="{{ $badgeBase }} {{ $badge($itemIssues === [] ? 'success' : 'warning') }}">{{ $itemIssues === [] ? 'Lengkap' : 'Belum Lengkap' }}</span>
                                @endif
                            </div>
                            <h4 class="mt-2 break-words font-bold text-gray-900 dark:text-white">{{ $item->title }}</h4>
                            @if($item->description)<p class="mt-1 whitespace-pre-line break-words text-sm text-gray-500 dark:text-gray-400">{{ $item->description }}</p>@endif
                            @if($isInternal && $itemIssues !== [])<p class="mt-1 break-words text-xs text-amber-700 dark:text-amber-300">{{ implode(' · ', $itemIssues) }}</p>@endif
                            <div class="mt-3 flex flex-wrap gap-2 [&:not(:has(*))]:hidden">
                                {{ $shown(($this->uploadFileAction)(['item' => $item->id])) }}
                                {{ $shown(($this->editItemAction)(['item' => $item->id])) }}
                                {{ $shown(($this->removeItemAction)(['item' => $item->id])) }}
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="{{ $emptyState }}">Belum ada item desain pada version ini.</p>
                @endforelse
            </div>
        </section>

        {{-- Spesifikasi Produksi --}}
        <section class="{{ $card }}" data-design-card="specs">
            <div class="{{ $cardHeader }}">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="{{ $iconBlock }} bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300"><x-heroicon-o-clipboard-document-list class="h-5 w-5" /></div>
                    <div class="min-w-0"><h3 class="{{ $sectionTitle }}">Spesifikasi Produksi</h3><p class="{{ $sectionHint }}">Hanya untuk item fisik.</p></div>
                </div>
            </div>
            <div class="space-y-3 p-5">
                @forelse($items->filter->isPhysical() as $item)
                    <article wire:key="design-spec-{{ $item->id }}" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <h4 class="break-words font-bold text-gray-900 dark:text-white">{{ $item->title }}</h4>
                        <dl class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach(['Dimensi' => $item->dimensions, 'Material' => $item->material_specification, 'Finishing' => $item->finishing, 'Warna/Cetak' => $item->color_print_notes, 'Sisi' => $item->sides, 'Catatan' => $item->production_notes] as $specLabel => $specValue)
                                <div class="min-w-0"><dt class="{{ $metaLabel }}">{{ $specLabel }}</dt><dd class="mt-1 whitespace-pre-line break-words text-sm text-gray-900 dark:text-white">{{ $specValue ?: 'Belum diisi' }}</dd></div>
                            @endforeach
                        </dl>
                    </article>
                @empty
                    <p class="{{ $emptyState }}">Version ini tidak mempunyai item fisik.</p>
                @endforelse
            </div>
        </section>

        {{-- Design Files --}}
        <section class="{{ $card }}" data-design-card="files">
            <div class="{{ $cardHeader }}">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="{{ $iconBlock }} bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300"><x-heroicon-o-folder-open class="h-5 w-5" /></div>
                    <div class="min-w-0"><h3 class="{{ $sectionTitle }}">Design Files</h3><p class="{{ $sectionHint }}">File privat. Tautan unduh berlaku singkat dan dibuat setelah akses diperiksa.</p></div>
                </div>
            </div>
            <div class="space-y-5 p-5">
                @foreach($filesByRole as $group)
                    <div wire:key="design-files-{{ $group['role']->value }}">
                        <div class="flex items-center gap-2">
                            <span class="{{ $badgeBase }} {{ $badge($group['role']->getColor()) }}">{{ $group['role']->getLabel() }}</span>
                            <span class="text-xs text-gray-500">{{ $group['entries']->count() }} file</span>
                        </div>
                        <div class="mt-2 space-y-2">
                            @forelse($group['entries'] as $entry)
                                <div wire:key="design-file-{{ $entry['file']->id }}" class="flex flex-col justify-between gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-700 lg:flex-row lg:items-center">
                                    <div class="min-w-0">
                                        <p class="break-words text-sm font-semibold text-gray-900 dark:text-white">{{ $entry['file']->original_name }}</p>
                                        <p class="mt-0.5 break-words text-xs text-gray-500 dark:text-gray-400">
                                            {{ $entry['item']->title }} · {{ strtoupper($entry['file']->extension) }} · {{ $entry['file']->humanSize() }} · {{ $entry['file']->uploader?->name ?? '—' }} · {{ $formatTime($entry['file']->uploaded_at) }}
                                            @if($entry['file']->reused_from_file_id) · Dipakai ulang dari revisi sebelumnya @endif
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                                        @if(in_array($group['role'], $downloadableRoles, true))
                                            <a href="{{ route('helpdesk.marketing-design-files.download', $entry['file']) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                                                <x-heroicon-o-arrow-down-tray class="h-4 w-4" /> Unduh
                                            </a>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Tidak ada akses unduh</span>
                                        @endif
                                        {{ $shown(($this->replaceFileAction)(['file' => $entry['file']->id])) }}
                                        {{ $shown(($this->removeFileAction)(['file' => $entry['file']->id])) }}
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-xl border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Belum ada file {{ strtolower($group['role']->getLabel()) }}.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Revision History --}}
        <section class="{{ $card }}" data-design-card="history">
            <div class="{{ $cardHeader }}">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="{{ $iconBlock }} bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300"><x-heroicon-o-clock class="h-5 w-5" /></div>
                    <div class="min-w-0"><h3 class="{{ $sectionTitle }}">Revision History</h3><p class="{{ $sectionHint }}">Version published dan superseded tetap read-only.</p></div>
                </div>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($history as $entry)
                    <div wire:key="design-history-{{ $entry->id }}" class="flex flex-col justify-between gap-3 p-5 sm:flex-row sm:items-center">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold text-gray-900 dark:text-white">{{ $entry->label() }}</span>
                                <span class="{{ $badgeBase }} {{ $badge($entry->status->getColor()) }}">{{ $entry->status->getLabel() }}</span>
                                <span class="text-xs text-gray-500">{{ $entry->items_count }} item</span>
                            </div>
                            <p class="mt-1 break-words text-sm text-gray-600 dark:text-gray-300">{{ $entry->change_summary ?: 'Tanpa ringkasan perubahan.' }}</p>
                            <p class="mt-0.5 break-words text-xs text-gray-500 dark:text-gray-400">Dibuat {{ $entry->creator?->name ?? '—' }}, {{ $formatTime($entry->created_at) }}@if($entry->published_at) · Publish {{ $entry->publisher?->name ?? '—' }}, {{ $formatTime($entry->published_at) }}@endif</p>
                        </div>
                        @if($isInternal && $entry->id !== $version?->id)
                            <button type="button" wire:click="selectVersion({{ $entry->id }})" wire:loading.attr="disabled" wire:target="selectVersion" class="shrink-0 self-start rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800 sm:self-auto">Lihat {{ $entry->label() }}</button>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Audit Activity --}}
        <section class="{{ $card }}" data-design-card="audit">
            <div class="{{ $cardHeader }}">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="{{ $iconBlock }} bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300"><x-heroicon-o-shield-check class="h-5 w-5" /></div>
                    <div class="min-w-0"><h3 class="{{ $sectionTitle }}">Audit Activity</h3><p class="{{ $sectionHint }}">{{ $auditLimit }} aktivitas terbaru.</p></div>
                </div>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($auditLogs as $log)
                    <div wire:key="design-audit-{{ $log->id }}" class="p-4 sm:px-5">
                        <p class="break-words text-sm font-semibold text-gray-900 dark:text-white">{{ $log->event->getLabel() }}@if($log->version) · {{ $log->version->label() }}@endif</p>
                        <p class="mt-0.5 break-words text-xs text-gray-500 dark:text-gray-400">{{ $log->actor?->name ?? 'Sistem' }} · {{ $formatTime($log->created_at) }}</p>
                        @if($log->reason)<p class="mt-1 whitespace-pre-line break-words text-sm text-gray-600 dark:text-gray-300">{{ $log->reason }}</p>@endif
                    </div>
                @empty
                    <p class="m-5 {{ $emptyState }}">Belum ada aktivitas.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
