@php
    $notifications = $this->notifications;
    $colorMap = [
        'success' => ['icon_bg' => 'bg-green-100 dark:bg-green-900/40', 'icon_color' => 'text-green-600'],
        'warning' => ['icon_bg' => 'bg-amber-100 dark:bg-amber-900/40', 'icon_color' => 'text-amber-600'],
        'danger'  => ['icon_bg' => 'bg-red-100 dark:bg-red-900/40',     'icon_color' => 'text-red-600'],
        'info'    => ['icon_bg' => 'bg-blue-100 dark:bg-blue-900/40',   'icon_color' => 'text-blue-600'],
    ];
@endphp

<div class="flex flex-col bg-blue-600 dark:bg-blue-900" style="min-height:100dvh">

    {{-- ════════════════════════════════════════════
         BLUE HEADER
    ════════════════════════════════════════════ --}}
    <div class="flex-shrink-0 px-5 pb-8 pt-14">
        <div class="flex items-center gap-3">
            <a href="{{ \App\Filament\Casual\Pages\ClockPage::getUrl() }}"
               class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-white transition active:bg-white/30">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-white">Notifikasi</h1>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         WHITE CONTENT CARD
    ════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto rounded-t-3xl bg-gray-50 pb-10 dark:bg-gray-950">

        @if($notifications->isEmpty())
            <div class="flex flex-col items-center gap-3 py-20 text-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <svg class="h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-400">Belum ada notifikasi</p>
            </div>
        @else
            @php
                $grouped = $notifications->groupBy(function ($n) {
                    if ($n->created_at->isToday()) return 'Hari ini';
                    if ($n->created_at->isYesterday()) return 'Kemarin';
                    return $n->created_at->locale('id')->isoFormat('D MMMM YYYY');
                });
            @endphp

            @foreach($grouped as $label => $items)
                <div class="px-5 pb-1 pt-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $label }}</p>
                </div>

                <div class="space-y-2 px-5">
                    @foreach($items as $notif)
                        @php
                            $data   = $notif->data;
                            $title  = $data['title'] ?? '';
                            $body   = $data['body'] ?? '';
                            $color  = $data['status'] ?? $data['color'] ?? 'info';
                            $c      = $colorMap[$color] ?? $colorMap['info'];
                        @endphp
                        <div class="flex items-start gap-3 rounded-2xl bg-white p-4 shadow-sm dark:bg-gray-900">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl {{ $c['icon_bg'] }}">
                                @if($color === 'success')
                                    <svg class="h-5 w-5 {{ $c['icon_color'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                    </svg>
                                @elseif($color === 'warning')
                                    <svg class="h-5 w-5 {{ $c['icon_color'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                    </svg>
                                @elseif($color === 'danger')
                                    <svg class="h-5 w-5 {{ $c['icon_color'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                    </svg>
                                @else
                                    <svg class="h-5 w-5 {{ $c['icon_color'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</p>
                                @if($body)
                                    <p class="mt-0.5 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $body }}</p>
                                @endif
                                <p class="mt-1.5 text-[10px] text-gray-400">{{ $notif->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif

    </div>

</div>
