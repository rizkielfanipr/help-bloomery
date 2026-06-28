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

        <title>{{ filament()->getBrandName() }} — Helpdesk</title>

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
    </body>
</html>
