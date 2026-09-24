<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }} — {{ $orgName }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Theme Initialization -->
    <script>
        (function() {
            var savedTheme = localStorage.getItem('zpm_theme');
            var systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>

    <!-- Tailwind CSS CDN for standalone legal page -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .custom-prose h1 { font-size: 1.875rem; font-weight: 800; margin-top: 1.5rem; margin-bottom: 0.75rem; }
        .custom-prose h2 { font-size: 1.5rem; font-weight: 700; margin-top: 1.25rem; margin-bottom: 0.5rem; }
        .custom-prose h3 { font-size: 1.25rem; font-weight: 600; margin-top: 1rem; margin-bottom: 0.5rem; }
        .custom-prose p { margin-bottom: 1rem; line-height: 1.7; }
        .custom-prose ul { list-style-type: disc; margin-left: 1.5rem; margin-bottom: 1rem; }
        .custom-prose ol { list-style-type: decimal; margin-left: 1.5rem; margin-bottom: 1rem; }
        .custom-prose li { margin-bottom: 0.25rem; }
        .custom-prose blockquote { border-left: 4px solid #0ea5e9; padding-left: 1rem; font-style: italic; margin: 1rem 0; opacity: 0.85; }
        .custom-prose a { color: #0284c7; text-decoration: underline; }
        .dark .custom-prose a { color: #38bdf8; }
        .custom-prose hr { border: 0; border-top: 1px solid rgba(148, 163, 184, 0.3); margin: 2rem 0; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
        }
    </style>
</head>
<body class="min-h-full bg-slate-50 dark:bg-slate-950 font-sans text-slate-800 dark:text-slate-100 flex flex-col antialiased transition-colors duration-200">
    <!-- Top Navigation Bar -->
    <header class="border-b border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md sticky top-0 z-30 no-print">
        <div class="max-w-4xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-3 group">
                @if(!empty($orgLogoUrl))
                    <img src="{{ $orgLogoUrl }}" alt="{{ $orgName }}" class="h-9 w-auto max-w-[140px] object-contain rounded-lg" />
                @else
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-sky-600 to-indigo-600 flex items-center justify-center text-white font-black text-lg shadow-sm group-hover:scale-105 transition-transform">
                        {{ strtoupper(substr($orgName, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <span class="font-bold text-sm text-slate-900 dark:text-white leading-tight block">{{ $orgName }}</span>
                    <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider block">Official Policy</span>
                </div>
            </a>

            <div class="flex items-center gap-2">
                <button
                    onclick="window.print()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 transition"
                    title="Print Document"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Print</span>
                </button>
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-semibold text-white bg-sky-600 hover:bg-sky-700 shadow-sm transition"
                >
                    <span>Back to App</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Document Main Container -->
    <main class="flex-1 max-w-4xl mx-auto w-full px-6 py-10">
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-8 sm:p-12 shadow-sm">
            <!-- Header section -->
            <div class="border-b border-slate-200 dark:border-slate-800 pb-6 mb-8">
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400 mb-2">
                    <span>Institutional Governance</span>
                    <span>•</span>
                    <span>Legal</span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ $documentTitle }}
                </h1>
                <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                    <span><strong>Organization:</strong> {{ $orgName }}</span>
                    @if(!empty($updatedAt))
                        <span>•</span>
                        <span><strong>Last Updated:</strong> {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('F j, Y') }}</span>
                    @endif
                    @if(!empty($orgSupportEmail))
                        <span>•</span>
                        <span><strong>Contact:</strong> <a href="mailto:{{ $orgSupportEmail }}" class="underline hover:text-sky-600">{{ $orgSupportEmail }}</a></span>
                    @endif
                </div>
            </div>

            <!-- Content body -->
            @if(!empty($content))
                <div class="custom-prose text-slate-700 dark:text-slate-300 text-sm leading-relaxed">
                    {!! $content !!}
                </div>
            @else
                <div class="py-12 text-center text-slate-500 dark:text-slate-400 space-y-3">
                    <svg class="w-12 h-12 mx-auto text-slate-400 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200">No Custom {{ $documentTitle }} Published</h3>
                    <p class="text-xs max-w-md mx-auto">
                        An administrator has not yet configured custom content for this document. Please contact the institution at
                        <a href="mailto:{{ $orgSupportEmail }}" class="text-sky-600 dark:text-sky-400 underline">{{ $orgSupportEmail }}</a> for official policy inquiries.
                    </p>
                </div>
            @endif
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-200/80 dark:border-slate-800/80 py-6 text-center text-xs text-slate-500 dark:text-slate-400 no-print">
        <div class="max-w-4xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>&copy; {{ date('Y') }} {{ $orgName }}. All rights reserved.</span>
            <div class="flex items-center gap-4">
                <a href="{{ route('legal.privacy') }}" class="hover:text-slate-800 dark:hover:text-slate-200">Privacy Policy</a>
                <span>•</span>
                <a href="{{ route('legal.terms') }}" class="hover:text-slate-800 dark:hover:text-slate-200">Terms of Service</a>
            </div>
        </div>
    </footer>
</body>
</html>
