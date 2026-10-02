<div
    x-data="{
        open: false,
        from: $wire.entangle('tableFilters.{{ $filterName }}.from').live,
        until: $wire.entangle('tableFilters.{{ $filterName }}.until').live,
        draftFrom: null,
        draftUntil: null,
        format(value) {
            if (! value) return '';
            const [year, month, day] = value.split('-');
            return `${day}/${month}/${year}`;
        },
        label() {
            if (! this.from && ! this.until) return 'Pilih rentang tanggal';
            return `${this.from ? this.format(this.from) : '...'} - ${this.until ? this.format(this.until) : '...'}`;
        },
        show() {
            this.draftFrom = this.from;
            this.draftUntil = this.until;
            this.open = true;
        },
        apply() {
            this.from = this.draftFrom;
            this.until = this.draftUntil;
            this.open = false;
        },
        clear() {
            this.draftFrom = null;
            this.draftUntil = null;
            this.from = null;
            this.until = null;
            this.open = false;
        },
    }"
    class="relative mt-2 min-w-48"
>
    <button type="button" @click.stop="open ? open = false : show()" class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-2.5 py-2 text-left text-xs font-normal normal-case tracking-normal text-gray-900 shadow-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
        <span class="truncate" x-text="label()"></span>
        <x-heroicon-o-calendar-days class="h-4 w-4 shrink-0 text-gray-400" />
    </button>

    <div x-show="open" x-cloak @click.outside="open = false" class="absolute left-0 z-50 mt-2 w-72 rounded-xl border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-900">
        <div class="space-y-3">
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                Dari Tanggal
                <input x-model="draftFrom" type="date" class="mt-1 block w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm dark:border-gray-600 dark:bg-gray-950">
            </label>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                Sampai Tanggal
                <input x-model="draftUntil" :min="draftFrom || null" type="date" class="mt-1 block w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm dark:border-gray-600 dark:bg-gray-950">
            </label>
        </div>
        <div class="mt-4 flex justify-end gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
            <button type="button" @click="clear()" class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-600 dark:border-gray-700 dark:text-gray-300">Clear</button>
            <button type="button" @click="apply()" class="rounded-md bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Apply</button>
        </div>
    </div>
</div>
