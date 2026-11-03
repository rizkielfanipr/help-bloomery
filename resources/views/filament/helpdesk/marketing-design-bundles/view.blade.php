@php
    use App\Enums\MarketingDesignBundleStatus;
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
                        {{ $shown($this->archiveBundleAction) }}
                        {{ $shown($this->restoreBundleAction) }}
                    </div>
                </div>
            </div>
            <div class="grid divide-y divide-gray-200 dark:divide-gray-700 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                <div class="flex items-start gap-3 p-5">
                    <x-heroicon-o-user-circle class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                    <div class="min-w-0"><p class="{{ $metaLabel }}">Dibuat Oleh</p><p class="{{ $metaValue }}">{{ $bundle->creator?->name ?? '—' }}</p></div>
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

        {{-- Design Items --}}
        <section class="{{ $card }}" data-design-card="items">
            <div class="{{ $cardHeader }}">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="{{ $iconBlock }} bg-violet-50 text-violet-600 dark:bg-violet-950/40 dark:text-violet-300"><x-heroicon-o-squares-2x2 class="h-5 w-5" /></div>
                    <div class="min-w-0"><h3 class="{{ $sectionTitle }}">Design Items</h3><p class="{{ $sectionHint }}">{{ $items->count() }} item pada bundle ini. File privat; tautan unduh berlaku singkat.</p></div>
                </div>
                <div class="shrink-0">{{ $shown($this->addItemAction) }}</div>
            </div>
            <div class="space-y-3 p-5">
                @forelse($items as $item)
                    @php
                        $itemIssues = $completeness->itemIssues($item);
                        $designFile = $item->file;
                    @endphp
                    <article wire:key="design-item-{{ $item->id }}" class="flex flex-col gap-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700 sm:flex-row">
                        <div class="h-28 w-full shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800 sm:w-36">
                            @if($canDownload && $designFile?->isImage())
                                <div x-data="{ failed: false }" class="h-full w-full">
                                    <img x-show="! failed" x-on:error="failed = true" src="{{ route('helpdesk.marketing-design-files.preview', $designFile) }}" loading="lazy" alt="Preview {{ $item->title }}" class="h-full w-full object-contain">
                                    <div x-show="failed" x-cloak class="flex h-full flex-col items-center justify-center gap-1 p-2 text-center text-xs text-gray-500"><x-heroicon-o-exclamation-triangle class="h-5 w-5" />Preview tidak tersedia</div>
                                </div>
                            @elseif($canDownload && $designFile?->isPreviewable())
                                <a href="{{ route('helpdesk.marketing-design-files.preview', $designFile) }}" target="_blank" rel="noopener" class="flex h-full flex-col items-center justify-center gap-1 p-2 text-center text-xs font-semibold text-blue-600 dark:text-blue-400"><x-heroicon-o-document-text class="h-6 w-6" />Buka PDF</a>
                            @elseif($designFile)
                                <div class="flex h-full flex-col items-center justify-center gap-1 p-2 text-center text-xs text-gray-500"><x-heroicon-o-archive-box class="h-6 w-6" />{{ strtoupper($designFile->extension) }}</div>
                            @else
                                <div class="flex h-full flex-col items-center justify-center gap-1 p-2 text-center text-xs text-gray-400"><x-heroicon-o-photo class="h-6 w-6" />Belum ada file</div>
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
                            @if($designFile)
                                <p class="mt-2 break-words text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $designFile->original_name }}</span>
                                    · {{ strtoupper($designFile->extension) }} · {{ $designFile->humanSize() }} · {{ $designFile->uploader?->name ?? '—' }} · {{ $formatTime($designFile->uploaded_at) }}
                                </p>
                            @endif
                            @if($isInternal && $itemIssues !== [])<p class="mt-1 break-words text-xs text-amber-700 dark:text-amber-300">{{ implode(' · ', $itemIssues) }}</p>@endif
                            <div class="mt-3 flex flex-wrap gap-2 [&:not(:has(*))]:hidden">
                                @if($canDownload)
                                    @if($designFile)
                                        <x-filament::button tag="a" :href="route('helpdesk.marketing-design-files.download', $designFile)" size="sm" color="gray" icon="heroicon-o-arrow-down-tray">Unduh</x-filament::button>
                                    @else
                                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" disabled title="Belum ada file desain">Unduh</x-filament::button>
                                    @endif
                                @endif
                                {{ $shown(($this->replaceFileAction)(['item' => $item->id, 'hasFile' => $designFile !== null])) }}
                                {{ $shown(($this->editItemAction)(['item' => $item->id])) }}
                                {{ $shown(($this->deleteItemAction)(['item' => $item->id])) }}
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="{{ $emptyState }}">Belum ada item desain pada bundle ini.</p>
                @endforelse
            </div>
        </section>

        {{-- Spesifikasi Produksi --}}
        <section class="{{ $card }}" data-design-card="specs">
            <div class="{{ $cardHeader }}">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="{{ $iconBlock }} bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300"><x-heroicon-o-clipboard-document-list class="h-5 w-5" /></div>
                    <div class="min-w-0"><h3 class="{{ $sectionTitle }}">Spesifikasi Produksi</h3><p class="{{ $sectionHint }}">Spesifikasi cetak setiap item fisik, diisi setelah item ditambahkan.</p></div>
                </div>
            </div>
            @php
                $physicalItems = $items->filter->isPhysical();
            @endphp
            @if($physicalItems->isEmpty())
                <div class="p-5"><p class="{{ $emptyState }}">Bundle ini tidak mempunyai item fisik.</p></div>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($physicalItems as $item)
                        @php
                            $specs = [
                                'Dimensi' => $item->dimensions,
                                'Material' => $item->material_specification,
                                'Finishing' => $item->finishing,
                                'Warna/Cetak' => $item->color_print_notes,
                                'Sisi' => $item->sides,
                                'Catatan Produksi' => $item->production_notes,
                            ];
                            $specComplete = filled($item->dimensions) && filled($item->material_specification);
                            $filledCount = collect($specs)->filter(fn ($value) => filled($value))->count();
                        @endphp
                        <li wire:key="design-spec-{{ $item->id }}" class="p-5">
                            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                @include('filament.helpdesk.marketing-design-bundles.partials.item-summary', ['item' => $item])
                                <div class="flex shrink-0 flex-wrap items-center gap-2">
                                    <span class="{{ $badgeBase }} {{ $badge($specComplete ? 'success' : 'warning') }}">{{ $specComplete ? 'Spesifikasi Lengkap' : 'Perlu Dilengkapi' }}</span>
                                    {{ $shown(($this->editSpecificationAction)(['item' => $item->id])) }}
                                </div>
                            </div>
                            @if($filledCount === 0)
                                <p class="mt-4 rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Spesifikasi produksi belum diisi.</p>
                            @else
                                <dl class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                                    @foreach($specs as $specLabel => $specValue)
                                        <div class="grid gap-1 px-4 py-2.5 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-4">
                                            <dt class="text-xs font-bold uppercase tracking-wide text-gray-400">{{ $specLabel }}</dt>
                                            @if(filled($specValue))
                                                <dd class="whitespace-pre-line break-words text-sm text-gray-900 dark:text-white">{{ $specValue }}</dd>
                                            @else
                                                <dd class="text-sm text-gray-400 dark:text-gray-500">Belum diisi</dd>
                                            @endif
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-filament-panels::page>
