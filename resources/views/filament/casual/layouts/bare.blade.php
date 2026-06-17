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

        <title>{{ filament()->getBrandName() }}</title>

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

    <body class="fi-body fi-panel-{{ filament()->getId() }}">
        {{ $slot }}

        @livewire(Filament\Livewire\Notifications::class)

        @filamentScripts(withCore: true)

        <script>
            window.addEventListener('theme-changed', function (e) {
                localStorage.setItem('casual-theme', e.detail);
            });
        </script>

        @stack('scripts')
    </body>
</html>
