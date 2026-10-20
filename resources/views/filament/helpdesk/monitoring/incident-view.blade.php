<x-filament-panels::page>
    @php
        $incident = $this->incident();
        $links = $this->linkedRecords();
        $canManage = $this->canManage();
        $sectionClass = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6';
        $labelClass = 'text-xs font-semibold text-gray-500 dark:text-gray-400';
        $valueClass = 'mt-0.5 whitespace-pre-line break-words text-sm text-gray-900 dark:text-white';
        $jakarta = fn ($date) => $date ? $date->copy()->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB' : '-';
    @endphp

    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300"><x-heroicon-o-fire class="h-6 w-6" aria-hidden="true" /></div>
                    <div class="min-w-0">
                        <p class="font-mono text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">{{ $incident->number }} · {{ $incident->environment }}</p>
                        <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $incident->title }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $incident->summary ?: 'Belum ada ringkasan.' }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <x-helpdesk.monitoring.status-badge :label="$incident->severity->getLabel()" :color="$incident->severity->getColor()" class="text-sm" />
                    <x-helpdesk.monitoring.status-badge :label="$incident->status->getLabel()" :color="$incident->status->getColor()" class="text-sm" />
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
            <div class="space-y-6">
                <section class="{{ $sectionClass }}">
                    <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Informasi</h3>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><dt class="{{ $labelClass }}">PIC</dt><dd class="{{ $valueClass }}">{{ $incident->owner?->name ?? 'Belum ditentukan' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Dibuat oleh</dt><dd class="{{ $valueClass }}">{{ $incident->is_automatic ? 'Otomatis dari alert' : ($incident->creator?->name ?? '-') }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Terdeteksi</dt><dd class="{{ $valueClass }}">{{ $jakarta($incident->detected_at) }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Diakui</dt><dd class="{{ $valueClass }}">{{ $jakarta($incident->acknowledged_at) }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Selesai</dt><dd class="{{ $valueClass }}">{{ $jakarta($incident->resolved_at) }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Dibuka ulang</dt><dd class="{{ $valueClass }}">{{ $incident->reopened_count }}×</dd></div>
                        <div class="sm:col-span-2"><dt class="{{ $labelClass }}">Layanan terdampak</dt><dd class="{{ $valueClass }}">{{ collect($incident->affected_services)->implode(', ') ?: '-' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="{{ $labelClass }}">Permintaan IT</dt><dd class="{{ $valueClass }}">{{ $incident->erpRepairRequest?->ticket_number ?? 'Tidak ditautkan' }}</dd></div>
                    </dl>
                </section>

                <section class="{{ $sectionClass }}">
                    <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Analisis</h3>
                    <dl class="space-y-4">
                        <div><dt class="{{ $labelClass }}">Root Cause</dt><dd class="{{ $valueClass }}">{{ $incident->root_cause ?: '-' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Resolution</dt><dd class="{{ $valueClass }}">{{ $incident->resolution ?: '-' }}</dd></div>
                        <div><dt class="{{ $labelClass }}">Preventive Action</dt><dd class="{{ $valueClass }}">{{ $incident->preventive_action ?: '-' }}</dd></div>
                    </dl>
                </section>

                <section class="{{ $sectionClass }}">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Record Terkait</h3>
                    <ul class="space-y-2">
                        @forelse ($links as $link)
                            <li class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700" wire:key="link-{{ $link['id'] }}">
                                <span class="min-w-0 break-all"><span class="text-xs font-semibold uppercase text-gray-500">{{ $link['type'] }}</span> ·
                                    @if ($link['url'])<a href="{{ $link['url'] }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">{{ $link['label'] }}</a>@else<span class="text-gray-700 dark:text-gray-300">{{ $link['label'] }}{{ $link['missing'] ? ' (sudah dihapus retensi)' : '' }}</span>@endif
                                </span>
                                @if ($canManage)
                                    <button type="button" wire:click="unlink({{ $link['id'] }})" wire:confirm="Hapus tautan ini?" class="shrink-0 rounded-md p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Hapus tautan {{ $link['label'] }}"><x-heroicon-o-x-mark class="h-4 w-4" aria-hidden="true" /></button>
                                @endif
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">Belum ada record yang ditautkan.</li>
                        @endforelse
                    </ul>
                </section>
            </div>

            <section class="{{ $sectionClass }}">
                <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Timeline (append-only)</h3>
                <ol class="relative space-y-4 border-l border-gray-200 pl-5 dark:border-gray-700">
                    @foreach ($incident->events as $event)
                        <li wire:key="event-{{ $event->id }}">
                            <span class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border-2 border-white bg-blue-500 dark:border-gray-900" aria-hidden="true"></span>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ str($event->event_type)->headline() }}
                                @if ($event->to_status)<span class="font-normal text-gray-500">· {{ $event->from_status ? str($event->from_status)->headline().' → ' : '' }}{{ str($event->to_status)->headline() }}</span>@endif
                            </p>
                            <p class="text-xs text-gray-500">{{ $event->actor?->name ?? 'System' }} · {{ $jakarta($event->created_at) }}</p>
                            @if ($event->note)<p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $event->note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>
</x-filament-panels::page>
