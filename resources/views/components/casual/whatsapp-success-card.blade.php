@props([
    'title',
    'subtitle',
    'whatsappUrl' => null,
    'ctaLabel' => 'Kirim ke WhatsApp',
    'resetMethod' => 'startNewRequest',
    'resetLabel' => 'Buat Permintaan Lain',
    'code' => null,
])

<div class="flex flex-col items-center gap-4 rounded-2xl border border-gray-200 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-900">
    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
        <svg class="h-7 w-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
        </svg>
    </div>
    <div>
        <p class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ $title }}</p>
        @if($whatsappUrl)
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
        @endif
    </div>

    @if($code)
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-800">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Kode Permintaan</p>
            <p class="mt-0.5 font-mono text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $code }}</p>
        </div>
    @endif

    @if($whatsappUrl)
        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener"
           class="flex w-full items-center justify-center gap-2 rounded-2xl bg-green-500 py-3.5 text-sm font-semibold text-white transition active:scale-95">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.472-.148-.67.15-.198.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12.001 2C6.478 2 2 6.477 2 12c0 1.876.51 3.634 1.397 5.144L2 22l4.994-1.362A9.945 9.945 0 0 0 12.001 22C17.523 22 22 17.523 22 12S17.523 2 12.001 2zm0 18.077a8.03 8.03 0 0 1-4.35-1.271l-.312-.186-3.116.85.833-3.037-.203-.312A8.02 8.02 0 0 1 3.923 12c0-4.454 3.624-8.077 8.078-8.077 4.453 0 8.076 3.623 8.076 8.077 0 4.454-3.623 8.077-8.076 8.077z"/></svg>
            {{ $ctaLabel }}
        </a>
    @endif

    {{ $slot }}

    <button type="button" wire:click="{{ $resetMethod }}"
            class="text-sm font-semibold text-blue-600 dark:text-blue-400">
        {{ $resetLabel }}
    </button>
</div>
