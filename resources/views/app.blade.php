<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Zoom Pool Manager') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Anti-FOUC Theme Script (Default: System Theme) -->
    <script>
        (function() {
            try {
                var storedTheme = localStorage.getItem('zpm_theme');
                var userTheme = "{{ $user?->theme ?? 'system' }}";
                var theme = storedTheme || (userTheme !== 'null' && userTheme !== '' ? userTheme : 'system');
                var isDark = false;
                
                if (theme === 'dark') {
                    isDark = true;
                } else if (theme === 'system') {
                    isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                }
                
                if (isDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {
                console.error('Error applying theme initial state', e);
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-200">
    @if ($demoMode)
        <div class="bg-amber-500 text-white text-xs font-semibold px-4 py-1.5 text-center flex items-center justify-center gap-2 shadow-sm z-50 relative">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>DEMO MODE ACTIVE — Simulated Zoom Credentials and Sandbox Operations</span>
        </div>
    @endif

    <div id="app" class="min-h-full"></div>
    <noscript>
        <nav aria-label="Primary Navigation">
            <a href="/app/dashboard">Dashboard</a>
        </nav>
        <script src="/js/zpm-spa.js"></script>
        <script>function themeManager() {}</script>
        <div class="p-4 text-center text-xs text-slate-500">
            <span>Made with ❤️ by Senthil Nasa</span>
            <span>•</span>
            <a href="https://github.com/senthilnasa" class="underline">https://github.com/senthilnasa</a>
        </div>
        @if(!empty($fallbackHtml))
            <div class="zpm-fallback-content">
                {!! $fallbackHtml !!}
            </div>
        @endif
    </noscript>

    <script>
        window.__ZPM__ = {
            user: @json($userData),
            demoMode: {{ $demoMode ? 'true' : 'false' }},
            appVersion: "{{ $appVersion }}",
            csrfToken: "{{ csrf_token() }}",
            branding: @json($branding),
        };
    </script>
</body>
</html>
