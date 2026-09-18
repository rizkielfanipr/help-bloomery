@props([
    'files' => [],
    'model' => 'attachments',
    'removeAction' => 'removeAttachment',
    'buttonText' => 'Tambah File',
    'accept' => null,
])

                @if(count($files) > 0)
                    <div class="mb-2 space-y-2">
                        @foreach($files as $index => $file)
                            <div class="flex items-center justify-between gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                                <div class="flex min-w-0 items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
                                    </svg>
                                    <span class="truncate text-xs text-slate-600 dark:text-slate-300">{{ $file->getClientOriginalName() }}</span>
                                </div>
                                <button type="button" wire:click="{{ $removeAction }}({{ $index }})"
                                        class="shrink-0 text-red-400 transition hover:text-red-600">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-4 transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:hover:border-blue-600 dark:hover:bg-blue-900/20">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                    </svg>
                    <span class="text-sm text-gray-400">{{ $buttonText }}</span>
                    <input type="file" wire:model="{{ $model }}" multiple @if($accept) accept="{{ $accept }}" @endif class="hidden">
                </label>
                @error($model . '.*') <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p> @enderror
