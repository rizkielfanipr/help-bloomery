@php
    $user = auth()->user();
    $firstName = explode(' ', $user->name)[0];
    $briefingData = $this->briefingData;

    $periodColors = [
        'daily'   => ['bg' => 'bg-violet-600', 'light' => 'bg-violet-50', 'icon' => 'text-violet-600', 'pill' => 'bg-violet-100 text-violet-700', 'bar' => 'bg-violet-500'],
        'weekly'  => ['bg' => 'bg-blue-600',   'light' => 'bg-blue-50',   'icon' => 'text-blue-600',   'pill' => 'bg-blue-100 text-blue-700',   'bar' => 'bg-blue-500'],
        'monthly' => ['bg' => 'bg-amber-500',  'light' => 'bg-amber-50',  'icon' => 'text-amber-500',  'pill' => 'bg-amber-100 text-amber-700', 'bar' => 'bg-amber-400'],
    ];
@endphp

<div class="flex flex-col bg-violet-600 dark:bg-violet-900"
     style="min-height:100dvh"
     x-data="{
         showModal: false,
         openModal() { this.showModal = true; },
         closeModal() { this.showModal = false; }
     }"
     @open-task-modal.window="openModal()"
     @close-task-modal.window="closeModal()">

    {{-- ════════════════════════════════════════════
         VIOLET HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('filament.casual.pages.clock-page') }}"
                   class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                    </svg>
                </a>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
                    </svg>
                </div>
                <span class="text-base font-semibold text-white">Daily Briefing</span>
            </div>
        </div>

        <p class="text-violet-200">
            {{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}
        </p>
        <p class="text-xl font-semibold text-white">Halo, {{ $firstName }}!</p>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-10 pt-6 dark:bg-gray-950">

        <div class="flex flex-col gap-5 px-5">

            @foreach($briefingData as $periodKey => $data)
                @php $colors = $periodColors[$periodKey]; @endphp

                <div class="rounded-2xl bg-white ring-1 ring-black/5 dark:bg-gray-900">

                    {{-- Period header --}}
                    <div class="flex items-center justify-between px-4 pb-3 pt-4">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl {{ $colors['light'] }}">
                                @if($periodKey === 'daily')
                                    <svg class="h-5 w-5 {{ $colors['icon'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                    </svg>
                                @elseif($periodKey === 'weekly')
                                    <svg class="h-5 w-5 {{ $colors['icon'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                                    </svg>
                                @else
                                    <svg class="h-5 w-5 {{ $colors['icon'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5"/>
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $data['label'] }}</p>
                                <p class="text-xs text-gray-400">{{ $data['completed'] }}/{{ $data['total'] }} tugas selesai</p>
                            </div>
                        </div>
                        <span class="rounded-lg px-2 py-0.5 text-xs font-medium {{ $colors['pill'] }}">
                            {{ $data['periodLabel'] }}
                        </span>
                    </div>

                    {{-- Progress bar --}}
                    <div class="mx-4 mb-3 h-1.5 overflow-hidden rounded-full bg-gray-100">
                        @php $pct = $data['total'] > 0 ? round($data['completed'] / $data['total'] * 100) : 0; @endphp
                        <div class="h-full rounded-full transition-all duration-500 {{ $colors['bar'] }}"
                             style="width: {{ $pct }}%"></div>
                    </div>

                    {{-- Task list --}}
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($data['tasks'] as $task)
                            <button type="button"
                                    wire:click="openTaskModal('{{ $task['key'] }}')"
                                    class="flex w-full items-start gap-3 px-4 py-3 text-left transition active:bg-gray-50 dark:active:bg-gray-800">

                                {{-- Checkbox icon --}}
                                <div class="mt-0.5 flex-shrink-0">
                                    @if($task['isCompleted'])
                                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-green-500">
                                            <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                            </svg>
                                        </div>
                                    @elseif($task['isHrChecked'] && $task['photoPath'])
                                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-400">
                                            <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="h-6 w-6 rounded-full border-2 border-gray-200 dark:border-gray-600"></div>
                                    @endif
                                </div>

                                {{-- Task info --}}
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium {{ $task['isCompleted'] ? 'text-gray-400 line-through dark:text-gray-500' : 'text-gray-800 dark:text-gray-200' }}">
                                        {{ $task['label'] }}
                                    </p>
                                    <div class="mt-0.5 flex items-center gap-2">
                                        @if($task['isHrChecked'])
                                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">Cek HR</span>
                                        @elseif($task['requiresPhoto'])
                                            <span class="text-xs text-gray-400">
                                                <svg class="inline h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                                                </svg>
                                                {{ $task['noteType'] }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">{{ $task['noteType'] }}</span>
                                        @endif
                                        @if($task['completedAt'])
                                            <span class="text-xs text-gray-400">{{ $task['completedAt']->format('H:i') }}</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Arrow --}}
                                <svg class="mt-1 h-4 w-4 flex-shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                </svg>
                            </button>
                        @endforeach
                    </div>

                </div>
            @endforeach

        </div>
    </div>

    {{-- ════════════════════════════════════════════
         TASK MODAL
    ════════════════════════════════════════════ --}}
    <div x-show="showModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-end"
         style="display:none">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"
             @click="closeModal(); $wire.set('activeTaskKey', null)"></div>

        {{-- Sheet --}}
        <div class="relative w-full rounded-t-3xl bg-white dark:bg-gray-900"
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

            <div class="px-5 pb-2 pt-2">
                @if($this->activeTaskKey)
                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ \App\Enums\BriefingTaskKey::from($this->activeTaskKey)->getLabel() }}
                    </p>
                    <p class="mt-0.5 text-sm text-gray-500">
                        {{ \App\Enums\BriefingTaskKey::from($this->activeTaskKey)->noteType() }}
                    </p>
                @endif
            </div>

            <div class="px-5 pb-8 pt-2" wire:key="task-form-{{ $taskModalKey }}">
                {{ $this->taskForm }}
            </div>

            <div class="flex gap-3 px-5 pb-10">
                <button type="button"
                        @click="closeModal(); $wire.set('activeTaskKey', null)"
                        class="flex-1 rounded-2xl border border-gray-200 py-3.5 text-sm font-semibold text-gray-600 transition active:bg-gray-50">
                    Batal
                </button>
                <button type="button"
                        wire:click="saveTask"
                        wire:loading.attr="disabled"
                        class="flex-1 rounded-2xl bg-violet-600 py-3.5 text-sm font-semibold text-white shadow-sm transition active:bg-violet-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="saveTask">Simpan</span>
                    <span wire:loading wire:target="saveTask">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</div>
