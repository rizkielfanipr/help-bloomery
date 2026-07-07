<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ __('filament-panels::layout.direction') ?? 'ltr' }}"
    class="fi"
>
    <head>
        <meta charset="utf-8" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />

        @php
            $pageTitle = isset($livewire) ? (string) $livewire->getTitle() : '';
            $docTitle  = $pageTitle ? $pageTitle . ' · Bloomery' : 'Bloomery';
        @endphp
        <title>{{ $docTitle }}</title>

        {{-- Favicon dinamis dark/light mode --}}
        <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}" media="(prefers-color-scheme: light)">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192-dark.png') }}" media="(prefers-color-scheme: dark)">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">

        {{-- PWA manifest & iOS --}}
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <meta name="theme-color" content="#2563eb">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Bloomery">
        <link rel="apple-touch-icon" href="{{ asset('icons/pwa-192.png') }}">

        <style>
            [x-cloak=''], [x-cloak='x-cloak'], [x-cloak='1'] { display: none !important; }
        </style>

        {{-- Initialise theme before any styles/scripts to avoid flash --}}
        <script>
            (function () {
                var saved = localStorage.getItem('casual-theme') || 'light';
                // Sync into the key Filament's JS reads so it respects our preference
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

        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    </head>

    <body class="fi-body fi-panel-{{ filament()->getId() }} bg-slate-200 dark:bg-slate-800"
          style="display:flex; justify-content:center; align-items:flex-start; min-height:100dvh;">

        <div style="width:100%; max-width:430px; min-height:100dvh; position:relative; box-shadow:0 0 0 1px rgba(0,0,0,.06), 0 8px 40px rgba(0,0,0,.18);">
            {{ $slot }}
        </div>

        @livewire(Filament\Livewire\Notifications::class)

        @filamentScripts(withCore: true)

        <script>
            window.addEventListener('theme-changed', function (e) {
                localStorage.setItem('casual-theme', e.detail);
            });
        </script>

        @stack('scripts')

        <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js', { scope: '/' });
            }
        </script>
    </body>
</html>
