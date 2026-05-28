<div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
    @foreach ($this->getStats() as $stat)
        <div class="group flex items-start gap-3.5 rounded-2xl border bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:bg-[#0F172A] dark:shadow-none {{ $stat['border'] }}">
            {{-- Icon --}}
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $stat['bg'] }}">
                <x-dynamic-component :component="$stat['icon']" class="h-5 w-5 {{ $stat['icon_color'] }}" />
            </div>
            {{-- Data --}}
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                <p class="mt-1 text-xl font-extrabold leading-none tracking-tight text-slate-900 dark:text-white">
                    {{ $stat['value'] }}
                </p>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ $stat['sub'] }}</p>
            </div>
        </div>
    @endforeach
</div>
