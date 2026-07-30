@php
    $instruction = $bomInstructions[$instructionBomId] ?? [];
@endphp

@if($canManageProject)
    <section
        wire:key="bom-instruction-{{ $instructionBomId }}"
        class="border-t border-violet-100 bg-violet-50/20 p-3 dark:border-violet-900/50 dark:bg-violet-950/10"
    >
        <div class="mb-2 flex items-center justify-between gap-2">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-violet-700 dark:text-violet-300">Informasi Tambahan</p>
                <p class="text-[10px] text-gray-500">Cara pembuatan, suhu, durasi, dan catatan proses.</p>
            </div>
            @if(!empty($instruction['updated_at']))
                <span class="text-[9px] text-emerald-600">Tersimpan</span>
            @endif
        </div>

        <textarea
            wire:model="bomInstructionTextDrafts.{{ $instructionBomId }}"
            rows="5"
            maxlength="50000"
            placeholder="Tulis cara pembuatan, suhu, durasi, atau informasi tambahan..."
            class="block min-h-28 w-full resize-y rounded-lg border border-violet-200 bg-white px-3 py-2 text-xs leading-5 text-gray-900 outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 dark:border-violet-800 dark:bg-gray-900 dark:text-white"
        ></textarea>

        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 flex-1">
                <input wire:model="bomInstructionInlineUploads.{{ $instructionBomId }}" type="file" multiple accept="image/jpeg,image/png,image/webp" class="block w-full rounded-md border border-gray-300 text-[10px] file:mr-2 file:border-0 file:bg-violet-100 file:px-2 file:py-1.5 file:font-bold file:text-violet-700 dark:border-gray-600 dark:bg-gray-800">
                <p wire:loading wire:target="bomInstructionInlineUploads.{{ $instructionBomId }}" class="mt-1 text-[9px] font-bold text-violet-600">Menyiapkan gambar...</p>
            </div>
            <button type="button" wire:click="saveInlineBomInstructionDraft({{ $instructionBomId }})" wire:loading.attr="disabled" wire:target="saveInlineBomInstructionDraft({{ $instructionBomId }})" class="shrink-0 rounded-md bg-violet-600 px-3 py-2 text-[10px] font-bold text-white hover:bg-violet-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="saveInlineBomInstructionDraft({{ $instructionBomId }})">Simpan Informasi</span>
                <span wire:loading wire:target="saveInlineBomInstructionDraft({{ $instructionBomId }})">Menyimpan...</span>
            </button>
        </div>

        @if(!empty($instruction['images']))
            <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                @foreach($instruction['images'] as $image)
                    <div class="relative overflow-hidden rounded-md border border-violet-100 dark:border-violet-900">
                        <img src="{{ $image['url'] }}" class="h-20 w-full object-cover" alt="Gambar proses">
                        <button type="button" wire:click="deleteBomInstructionImage({{ $instructionBomId }}, '{{ base64_encode($image['path']) }}')" wire:confirm="Hapus gambar ini?" class="absolute right-1 top-1 rounded bg-red-600 p-1 text-white"><x-heroicon-o-trash class="h-3 w-3" /></button>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endif
