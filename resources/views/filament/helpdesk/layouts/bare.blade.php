<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ __('filament-panels::layout.direction') ?? 'ltr' }}"
    class="fi"
>
    <head>
        <meta charset="utf-8" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />

        @php
            $pageTitle = isset($livewire) ? (string) $livewire->getTitle() : '';
            $docTitle  = $pageTitle ? $pageTitle . ' · Helpdesk' : 'Bloomery Helpdesk';
        @endphp
        <title>{{ $docTitle }}</title>

        {{-- Favicon --}}
        <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/pwa-192.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

        {{-- PWA manifest & iOS --}}
        <link rel="manifest" href="{{ asset('manifest-helpdesk.json') }}">
        <meta name="theme-color" content="#2563eb">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Bloomery Helpdesk">
        <link rel="apple-touch-icon" href="{{ asset('icons/pwa-192.png') }}">

        <style>
            [x-cloak=''], [x-cloak='x-cloak'], [x-cloak='1'] { display: none !important; }
        </style>

        <script>
            (function () {
                var saved = localStorage.getItem('helpdesk-theme') || 'light';
                localStorage.setItem('theme', saved);
                if (saved === 'dark') document.documentElement.classList.add('dark');
            })();
        </script>

        @filamentStyles

        {{ filament()->getTheme()->getHtml() }}
        {{ filament()->getFontHtml() }}

        <style>
            :root {
                --font-family: '{!! filament()->getFontFamily() !!}';
                --default-theme-mode: {{ filament()->getDefaultThemeMode()->value }};
            }
            html.fi { --livewire-progress-bar-color: var(--primary-500); }
            body { margin: 0; padding: 0; }
        </style>

        @stack('styles')
    </head>

    <body class="fi-body fi-panel-{{ filament()->getId() }}">
        {{ $slot }}

        @livewire(Filament\Livewire\Notifications::class)

        @filamentScripts(withCore: true)

        @stack('scripts')

        <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js', { scope: '/helpdesk' });
            }
        </script>
    </body>
</html>
