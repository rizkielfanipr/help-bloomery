@php($record = $getRecord())

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="max-h-[60dvh] space-y-3 overflow-y-auto overscroll-contain pr-1"
        x-data="{ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }} }"
    >
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Klik kartu supplier yang akan disetujui.
        </p>

        @forelse ($record->sourcings as $sourcing)
            <label
                class="block cursor-pointer rounded-xl border p-4 shadow-sm transition"
                :class="String(state) === '{{ $sourcing->id }}'
                    ? 'border-primary-500 bg-primary-50/70 ring-2 ring-primary-200 dark:bg-primary-950/30 dark:ring-primary-800'
                    : 'border-gray-200 bg-white hover:border-primary-300 dark:border-gray-700 dark:bg-gray-900'"
            >
                <div class="flex items-start gap-3">
                    <input
                        x-model="state"
                        type="radio"
                        value="{{ $sourcing->id }}"
                        class="mt-1 border-gray-300 text-primary-600 focus:ring-primary-500"
                    />

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $sourcing->supplier_name }}
                            </p>
                            <p class="whitespace-nowrap text-base font-bold text-primary-700 dark:text-primary-300">
                                Rp{{ number_format((float) $sourcing->price, 0, ',', '.') }}
                            </p>
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-5">
                            @foreach ([
                                'Merk' => $sourcing->brand ?: '—',
                                'MOQ' => $sourcing->moq ?: '—',
                                'Lead Time' => $sourcing->lead_time_days ? $sourcing->lead_time_days.' hari' : '—',
                                'Kontak' => $sourcing->contact_name ?: '—',
                                'Telepon' => $sourcing->contact_phone ?: '—',
                            ] as $label => $value)
                                <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5">
                                    <dt class="text-gray-400">{{ $label }}</dt>
                                    <dd class="mt-0.5 font-medium text-gray-800 dark:text-gray-200">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        @if ($sourcing->notes)
                            <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $sourcing->notes }}</p>
                        @endif

                        @if ($sourcing->attachmentUrl())
                            <a
                                href="{{ $sourcing->attachmentUrl() }}"
                                target="_blank"
                                class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700"
                                @click.stop
                            >
                                <x-heroicon-o-paper-clip class="h-3.5 w-3.5" />
                                Lihat Lampiran
                            </a>
                        @endif

                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-gray-100 pt-3 text-[11px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <span>Dibuat: {{ $sourcing->created_at?->format('d M Y H:i') ?? '—' }}</span>
                            <span>Diupdate: {{ $sourcing->updated_at?->format('d M Y H:i') ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </label>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada supplier yang diajukan.</p>
        @endforelse
    </div>
</x-dynamic-component>
