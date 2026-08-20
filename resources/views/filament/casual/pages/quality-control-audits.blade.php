@php
    $user = auth()->user();
    $firstName = explode(' ', $user->name)[0];
    $drafts = $this->draftAudits;
@endphp

<div x-data="{ showStartAuditModal: false }"
     @open-start-audit-modal.window="showStartAuditModal = true"
     @close-start-audit-modal.window="showStartAuditModal = false">
<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('filament.casual.pages.launcher-page') }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z"/>
                </svg>
            </div>
            <span class="text-base font-semibold text-white">Quality Control</span>
        </div>

        <p class="text-blue-200">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
        <p class="text-xl font-semibold text-white">Halo, {{ $firstName }}!</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-28 pt-6 dark:bg-gray-950">

        <div class="flex flex-col gap-5 px-5">

            <button wire:click="openStartAuditModal"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-sm transition active:scale-[0.98]">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Audit Baru
            </button>

            <div class="rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900">

                {{-- Section header --}}
                <div class="flex items-center justify-between px-4 pb-3 pt-4">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                            <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Sedang Berjalan</p>
                            <p class="text-xs text-gray-400">{{ $drafts->count() }} audit belum disubmit</p>
                        </div>
                    </div>
                </div>

                @if($drafts->isEmpty())
                    <div class="flex flex-col items-center gap-3 px-5 pb-6 pt-2 text-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-900/20">
                            <svg class="h-7 w-7 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-400">Tidak ada audit yang sedang berjalan. Tekan "Audit Baru" untuk mulai.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($drafts as $audit)
                            @php
                                $items = $audit->items;
                                $answered = $items->whereNotNull('result')->count();
                                $total = $items->count();
                            @endphp
                            <a href="{{ \App\Filament\Casual\Pages\QualityControlAuditDetail::getUrl(['record' => $audit->id], panel: 'casual') }}"
                               class="flex w-full items-start gap-3 px-4 py-3.5 text-left transition active:bg-gray-50 dark:active:bg-gray-800/50">
                                <div class="mt-0.5 flex-shrink-0">
                                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-400">
                                        <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $audit->branch?->name ?? 'Tanpa Store' }}</p>
                                    <div class="mt-0.5 flex items-center gap-1.5">
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ $answered }}/{{ $total }} poin</span>
                                        <span class="text-xs text-gray-400">{{ $audit->audit_date?->format('d M Y') }}</span>
                                    </div>
                                </div>
                                <svg class="mt-1 h-4 w-4 flex-shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                </svg>
                            </a>
                        @endforeach
                    </div>
                @endif

            </div>

        </div>
    </div>

</div>

{{-- ════════════════════════════════════════════
     START AUDIT MODAL (bottom sheet)
════════════════════════════════════════════ --}}
<div x-show="showStartAuditModal"
     x-cloak
     class="fixed inset-0 z-50 flex items-end"
     style="display:none">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"
         @click="showStartAuditModal = false" wire:click="cancelStartAuditModal"></div>

    {{-- Sheet --}}
    <div class="relative max-h-[85vh] w-full overflow-y-auto rounded-t-3xl bg-white dark:bg-gray-900"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full">

        {{-- Handle --}}
        <div class="flex justify-center pb-1 pt-3">
            <div class="h-1 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
        </div>

        {{-- Title --}}
        <div class="px-5 pb-2 pt-2">
            <p class="text-base font-semibold text-gray-900 dark:text-white">Audit Baru</p>
        </div>

        {{-- Form body --}}
        <div class="px-5 pb-4 pt-2">
            {{ $this->startAuditForm }}
        </div>

        {{-- Action buttons --}}
        <div class="flex gap-3 px-5 pb-10">
            <button type="button"
                    @click="showStartAuditModal = false" wire:click="cancelStartAuditModal"
                    class="flex-1 rounded-2xl border border-gray-200 py-3.5 text-sm font-semibold text-gray-600 transition active:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                Batal
            </button>
            <button type="button"
                    wire:click="submitStartAudit"
                    wire:loading.attr="disabled"
                    class="flex-1 rounded-2xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-sm transition active:bg-blue-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="submitStartAudit">Mulai Audit</span>
                <span wire:loading wire:target="submitStartAudit">Memproses...</span>
            </button>
        </div>
    </div>
</div>

<x-quality-control.bottom-nav active="form" />
</div>
