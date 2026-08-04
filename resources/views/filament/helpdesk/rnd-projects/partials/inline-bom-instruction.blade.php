@php
    $instruction = $bomInstructions[$instructionBomId] ?? [];
    $instructionHtml = (string) ($instruction['content_html'] ?? '');
@endphp

@if($canManageProject)
    <section
        wire:key="bom-instruction-{{ $instructionBomId }}"
        class="border-t border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-900/40"
        x-data="bomQuillEditor({
            bomId: {{ $instructionBomId }},
            uploadUrl: @js(route('helpdesk.rnd-products.bom-instruction-images.store', ['project' => $projectId, 'product' => $productId, 'bom' => $instructionBomId])),
            initialHtml: @js($instructionHtml),
        })"
        x-init="init()"
    >
        <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-bold text-gray-800 dark:text-gray-100">Informasi Tambahan & Cara Pembuatan</p>
                <p class="text-[11px] text-gray-500">Tuliskan tahapan, suhu, durasi, catatan proses, dan sisipkan foto pada posisi yang diperlukan.</p>
            </div>
            @if(!empty($instruction['updated_at']))
                <span class="text-[10px] font-semibold text-emerald-600">Tersimpan</span>
            @endif
        </div>

        <div wire:ignore class="overflow-hidden rounded-lg border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-900">
            <div x-ref="toolbar" class="bom-quill-toolbar">
                <span class="ql-formats">
                    <select class="ql-header"><option selected></option><option value="2"></option><option value="3"></option></select>
                </span>
                <span class="ql-formats"><button class="ql-bold"></button><button class="ql-italic"></button><button class="ql-underline"></button></span>
                <span class="ql-formats"><button class="ql-list" value="ordered"></button><button class="ql-list" value="bullet"></button></span>
                <span class="ql-formats"><button class="ql-blockquote"></button><button class="ql-link"></button><button class="ql-image"></button></span>
                <span class="ql-formats"><button class="ql-clean"></button></span>
            </div>
            <div x-ref="editor" class="bom-quill-editor"></div>
        </div>
        <input x-ref="imageInput" x-on:change="insertFiles($event.target.files); $event.target.value = ''" type="file" multiple accept="image/jpeg,image/png,image/webp" class="hidden">
        <p class="mt-1 text-[10px] text-gray-400">JPG, PNG, atau WebP. Maksimal 8 foto. Tarik & lepas atau paste foto langsung ke editor, atau gunakan ikon gambar pada toolbar.</p>

        <div class="mt-3 flex items-center justify-end gap-2">
            <span x-show="uploading" x-cloak class="text-[11px] font-semibold text-gray-500">Mengunggah foto...</span>
            <button type="button" x-on:click="save()" :disabled="saving || uploading" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                <x-heroicon-o-check class="h-4 w-4" />
                <span x-text="saving ? 'Menyimpan...' : 'Simpan Informasi'"></span>
            </button>
        </div>
    </section>
@endif
