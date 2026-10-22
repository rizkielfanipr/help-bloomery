{{-- One evidence image upload with preview, progress, and remove. --}}
@php $file = data_get($this, $model); @endphp
<div x-data="{ progress: 0, uploading: false }"
     x-on:livewire-upload-start="uploading = true; progress = 0"
     x-on:livewire-upload-finish="uploading = false"
     x-on:livewire-upload-error="uploading = false"
     x-on:livewire-upload-progress="progress = $event.detail.progress">
    <p class="mb-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400">{{ $label }} <span class="text-red-400">*</span> <span class="ml-1 font-normal text-slate-400">(JPG/PNG/WEBP · maks. 5 MB)</span></p>

    @if($file && method_exists($file, 'isPreviewable') && $file->isPreviewable())
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <img src="{{ $file->temporaryUrl() }}" alt="{{ $label }}" class="h-40 w-full object-contain">
            <button type="button" wire:click="$set('{{ $model }}', null)" aria-label="Hapus {{ $label }}"
                    class="absolute right-2 top-2 inline-flex items-center gap-1 rounded-lg border border-red-200 bg-white px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 dark:border-red-900 dark:bg-gray-900">
                <x-heroicon-o-trash class="h-3.5 w-3.5" /> Hapus
            </button>
        </div>
    @else
        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 bg-white py-3.5 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:bg-gray-800/50 dark:hover:border-blue-600 dark:hover:bg-blue-900/20">
            <x-heroicon-o-camera class="h-5 w-5 text-gray-400" />
            <span class="text-sm text-gray-500">Ambil / Pilih Foto</span>
            <input type="file" wire:model="{{ $model }}" accept="image/jpeg,image/png,image/webp" class="sr-only" aria-label="{{ $label }}">
        </label>
    @endif

    <div x-show="uploading" x-cloak class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
        <div class="h-full bg-blue-500 transition-all" :style="`width: ${progress}%`"></div>
    </div>
    @error($model) <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
</div>
