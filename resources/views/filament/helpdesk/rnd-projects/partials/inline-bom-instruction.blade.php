@php
    $instruction = $bomInstructions[$instructionBomId] ?? [];
    $instructionHtml = (string) ($instruction['content_html'] ?? '');
@endphp

@if($canManageProject)
    <section
        wire:key="bom-instruction-{{ $instructionInstanceKey }}"
        class="border-t border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-900/40"
    >
        <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-bold text-gray-800 dark:text-gray-100">Informasi Tambahan & Cara Pembuatan</p>
                <p class="text-[11px] text-gray-500">Tuliskan tahapan, suhu, durasi, catatan proses, dan sisipkan foto pada posisi yang diperlukan.</p>
            </div>
            <div class="flex items-center gap-2">
                @if(!empty($instruction['updated_at']))
                    <span class="text-[10px] font-semibold text-emerald-600">Tersimpan</span>
                @endif
                <button
                    type="button"
                    wire:click='mountAction("editBomInstruction", { bomId: {{ $instructionBomId }}, bomName: @js($bomName ?? "BOM") })'
                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700"
                >
                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                    Edit Informasi
                </button>
            </div>
        </div>

        <div class="prose prose-sm max-w-none rounded-lg border border-gray-200 bg-white p-4 text-gray-700 dark:prose-invert dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
            @if($instructionHtml !== '')
                {!! $instructionHtml !!}
            @else
                <p class="m-0 text-xs italic text-gray-400">Belum ada informasi tambahan atau cara pembuatan.</p>
            @endif
        </div>
    </section>
@endif
