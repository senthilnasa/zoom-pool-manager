<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if(session('success')) <meta name="flash-success" content="{{ session('success') }}"> @endif
    @if(session('error')) <meta name="flash-error" content="{{ session('error') }}"> @endif
    @if(session('warning')) <meta name="flash-warning" content="{{ session('warning') }}"> @endif

    <title>@yield('title', config('app.name', 'Zoom Pool Manager'))</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    <!-- Theme Initialization (System preference by default with localStorage persistence) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('zpm_theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>

    <!-- Tailwind CSS (with light & dark mode glassmorphism) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            900: '#0c4a6e',
                        },
                    },
                    boxShadow: {
                        'glass': '0 8px 32px 0 rgba(0, 0, 0, 0.08)',
                        'glass-dark': '0 8px 32px 0 rgba(0, 0, 0, 0.37)',
                        'glow': '0 0 20px -5px rgba(14, 165, 233, 0.35)',
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <!-- ZPM SPA & AJAX Layer -->
    <script defer src="{{ asset('js/zpm-spa.js') }}"></script>

    <style>
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.5); border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(100, 116, 139, 0.8); }
        .dark ::-webkit-scrollbar-thumb { background: rgba(51, 65, 85, 0.8); }
        .dark ::-webkit-scrollbar-thumb:hover { background: rgba(100, 116, 139, 1); }
    </style>
    @stack('styles')
</head>
<body class="h-full bg-slate-50 dark:bg-slate-950 font-sans text-slate-800 dark:text-slate-100 flex overflow-x-hidden antialiased transition-colors duration-200"
      x-data="themeManager()"
      x-init="initTheme()">

    @auth
        <!-- Mobile Sidebar Backdrop -->
        <div x-show="mobileMenuOpen"
             x-cloak
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 lg:hidden"
             @click="mobileMenuOpen = false"></div>

        <!-- Left-Side Navigation Sidebar (Light & Dark Glassmorphism) -->
        <aside :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="fixed inset-y-0 left-0 z-50 w-64 bg-white/90 dark:bg-slate-900/90 backdrop-blur-2xl border-r border-slate-200 dark:border-white/5 flex flex-col transition-transform duration-300 ease-in-out shadow-sm dark:shadow-none">
            
            <!-- Branding Header -->
            <div class="h-16 flex items-center justify-between px-5 border-b border-slate-100 dark:border-white/5">
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-3 group">
                    @php
                        $customLogo = \App\Domain\Settings\Models\Setting::get('org.logo_url');
                        $orgName = \App\Domain\Settings\Models\Setting::get('org.name', config('app.name', 'Zoom Pool Manager'));
                    @endphp
                    @if(!empty($customLogo))
                        <img src="{{ $customLogo }}" alt="{{ $orgName }}" class="w-10 h-10 object-contain rounded-xl shadow-sm group-hover:scale-105 transition duration-200" />
                    @else
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-600 via-indigo-600 to-purple-600 flex items-center justify-center text-white font-black text-xl shadow-glow group-hover:scale-105 transition duration-200">
                            {{ strtoupper(substr($orgName, 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <div class="font-bold text-sm tracking-tight text-slate-900 dark:text-white flex items-center space-x-2">
                            <span class="truncate max-w-[130px]" title="{{ $orgName }}">{{ $orgName }}</span>
                            <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-sky-500/10 dark:bg-sky-500/20 border border-sky-500/30 text-sky-600 dark:text-sky-400 rounded-md">v{{ config('zpm.version', '1.0.0') }}</span>
                        </div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400">Resource Manager</p>
                    </div>
                </a>
                <button type="button" @click="mobileMenuOpen = false" class="lg:hidden text-slate-400 hover:text-slate-700 dark:hover:text-white p-1" aria-label="Close menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6" role="navigation" aria-label="Primary Navigation">
                
                <!-- Section: Workspace -->
                <div>
                    <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Workspace</div>
                    <div class="space-y-1">
                        <a href="{{ route('dashboard') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('dashboard') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            <span>Dashboard</span>
                        </a>
                        <a href="{{ route('meetings.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('meetings.*') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <span>Meetings</span>
                        </a>
                        <a href="{{ route('series.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('series.*') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span>Recurring Series</span>
                        </a>
                        <a href="{{ route('calendar') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('calendar') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>Calendar</span>
                        </a>
                    </div>
                </div>

                <!-- Section: Governance & Rules -->
                <div>
                    <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Governance</div>
                    <div class="space-y-1">
                        <a href="{{ route('approvals.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('approvals.*') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Approvals</span>
                        </a>
                        <a href="{{ route('workflows.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('workflows.*') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                            <span>Workflow Rules</span>
                        </a>
                        <a href="{{ route('quotas.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('quotas.*') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span>Quotas & Limits</span>
                        </a>
                    </div>
                </div>

                <!-- Section: Media & Sync -->
                <div>
                    <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Recordings & Sync</div>
                    <div class="space-y-1">
                        <a href="{{ route('recordings.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('recordings.*') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>
                            <span>Cloud Recordings</span>
                        </a>
                        <a href="{{ route('drift.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('drift.*') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <span>Drift Reconciliation</span>
                        </a>
                    </div>
                </div>

                <!-- Section: Administration & Operations -->
                <div>
                    <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Operations</div>
                    <div class="space-y-1">
                        <a href="{{ route('admin.health') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('admin.health') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span>Health & Diagnostics</span>
                        </a>
                        <a href="{{ route('admin.alerts') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('admin.alerts') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <span>Anomaly Alerts</span>
                        </a>
                        <a href="{{ route('admin.backups') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('admin.backups') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            <span>Database Backups</span>
                        </a>
                        <a href="{{ route('admin.emergency') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('admin.emergency') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>Emergency Panel</span>
                        </a>
                        <a href="{{ route('admin.outbound-webhooks.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('admin.outbound-webhooks.*') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <span>Outbound Webhooks</span>
                        </a>
                        <a href="{{ route('webhooks.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('webhooks.*') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                            <span>Inbound Webhooks</span>
                        </a>
                        <a href="{{ route('admin.mail.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('admin.mail.*') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span>Mail Settings</span>
                        </a>
                        <a href="{{ route('admin.api-keys.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('admin.api-keys.*') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            <span>API Keys</span>
                        </a>
                        @if(Route::has('admin.system.updates.index'))
                            <a href="{{ route('admin.system.updates.index') }}" class="flex items-center space-x-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('admin.system.updates.*') ? 'bg-sky-50 text-sky-600 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-400 dark:border-sky-500/30 font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}">
                                <svg class="w-4 h-4 shrink-0 text-sky-500 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>System Updates</span>
                            </a>
                        @endif
                    </div>
                </div>

            </nav>

            <!-- User Profile & Session Footer in Sidebar -->
            <div class="p-3 border-t border-slate-100 dark:border-white/5 bg-slate-50/60 dark:bg-slate-950/40">
                <div class="flex items-center justify-between p-2 rounded-xl bg-white dark:bg-slate-800/40 border border-slate-200/80 dark:border-white/5 shadow-sm dark:shadow-none">
                    <div class="flex items-center space-x-2.5 overflow-hidden">
                        <div class="w-8 h-8 rounded-lg bg-sky-500/10 dark:bg-sky-500/20 text-sky-600 dark:text-sky-300 font-bold flex items-center justify-center shrink-0 text-xs border border-sky-500/20 dark:border-sky-500/30">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <div class="truncate">
                            <p class="text-xs font-semibold text-slate-800 dark:text-white truncate">{{ auth()->user()->name }}</p>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-500 dark:hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition" title="Log out" aria-label="Log out">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>
    @endauth

    <!-- Main Content Area Wrapper -->
    <div class="flex-1 flex flex-col min-w-0 {{ auth()->check() ? 'lg:pl-64' : '' }} min-h-screen">

        <!-- Demo Mode Announcement Banner -->
        @if(config('app.demo'))
            <div class="bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 text-slate-950 px-4 py-1.5 text-xs font-bold text-center tracking-wide shadow-sm flex items-center justify-center space-x-2" role="alert" aria-live="polite">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>{{ __('zpm.demo.banner') }}</span>
            </div>
        @endif

        <!-- Modern Top Header Bar (Light & Dark Glassmorphism) -->
        <header class="h-16 bg-white/70 dark:bg-slate-900/60 backdrop-blur-xl border-b border-slate-200/80 dark:border-white/5 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8 shadow-sm dark:shadow-none" role="banner">
            <div class="flex items-center space-x-4">
                @auth
                    <!-- Mobile Hamburger Button -->
                    <button type="button" @click="mobileMenuOpen = true" class="lg:hidden p-2 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800/60 transition" aria-label="Open navigation menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                @endauth
                <div>
                    <h1 class="text-sm font-semibold text-slate-900 dark:text-white">@yield('page_title', 'Zoom Pool Manager')</h1>
                </div>
            </div>

            <div class="flex items-center space-x-3 text-xs">
                <!-- Theme Toggle Button (Light, Dark, System) -->
                <button type="button"
                        @click="cycleTheme()"
                        :title="'Current Theme: ' + themeMode + ' (Click to change)'"
                        class="p-2 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60 rounded-xl transition flex items-center space-x-1"
                        aria-label="Toggle theme">
                    <!-- Sun Icon (shown when Light) -->
                    <svg x-show="themeMode === 'light'" class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <!-- Moon Icon (shown when Dark) -->
                    <svg x-show="themeMode === 'dark'" class="w-4 h-4 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <!-- Desktop Icon (shown when System) -->
                    <svg x-show="themeMode === 'system'" class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </button>

                @auth
                    <!-- Notification Bell -->
                    @php
                        $unreadCount = auth()->user()->unreadNotifications()->count();
                    @endphp
                    <a href="{{ route('notifications.index') }}" class="relative p-2 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60 rounded-xl transition" aria-label="View notifications">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        @if($unreadCount > 0)
                            <span class="absolute top-1.5 right-1.5 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-black leading-none text-white transform translate-x-1/3 -translate-y-1/3 bg-rose-500 rounded-full shadow-lg shadow-rose-500/50">
                                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                            </span>
                        @endif
                    </a>

                    <a href="{{ route('meetings.create') }}" class="hidden sm:inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-medium text-xs shadow-glow transition duration-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Schedule Meeting</span>
                    </a>
                @else
                    <span class="text-xs text-slate-500 dark:text-slate-400">Independent Open-Source Software</span>
                @endauth
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
            @yield('content')
        </main>

        <!-- Application Footer Attribution -->
        <footer class="bg-white/50 dark:bg-slate-900/40 backdrop-blur-md border-t border-slate-200 dark:border-white/5 py-4 text-center text-xs text-slate-500 dark:text-slate-400">
            <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="flex items-center space-x-2 text-slate-600 dark:text-slate-300">
                    <span>{{ config('zpm.attribution.label', 'Made with ❤️ by Senthil Nasa') }}</span>
                    <span>•</span>
                    <a href="{{ config('zpm.attribution.url', 'https://github.com/senthilnasa') }}" target="_blank" rel="noopener noreferrer" class="text-sky-600 dark:text-sky-400 hover:underline underline-offset-2">GitHub</a>
                </div>
                <div class="flex items-center space-x-3 text-slate-500 dark:text-slate-400 text-[11px]">
                    <a href="{{ route('legal.privacy') }}" class="hover:text-slate-700 dark:hover:text-slate-200 transition">Privacy</a>
                    <span>•</span>
                    <a href="{{ route('legal.terms') }}" class="hover:text-slate-700 dark:hover:text-slate-200 transition">Terms</a>
                    <span>•</span>
                    @if(Route::has('admin.system.updates.index'))
                        <a href="{{ route('admin.system.updates.index') }}" class="hover:text-slate-700 dark:hover:text-slate-200 transition">v{{ config('zpm.version', '1.0.0') }}</a>
                    @else
                        <span>v{{ config('zpm.version', '1.0.0') }}</span>
                    @endif
                    <span>•</span>
                    <p class="max-w-md truncate">Not affiliated with or endorsed by Zoom Video Communications, Inc.</p>
                </div>
            </div>
        </footer>

    </div>

    <!-- Global Toast Container -->
    <div id="zpm-toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col space-y-3 max-w-sm w-full pointer-events-none px-4 sm:px-0"></div>

    <script>
    function themeManager() {
        return {
            mobileMenuOpen: false,
            themeMode: localStorage.getItem('zpm_theme') || 'system',

            initTheme() {
                this.applyTheme(this.themeMode);
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                    if (this.themeMode === 'system') {
                        this.applyTheme('system');
                    }
                });
            },

            cycleTheme() {
                if (this.themeMode === 'system') {
                    this.setTheme('light');
                } else if (this.themeMode === 'light') {
                    this.setTheme('dark');
                } else {
                    this.setTheme('system');
                }
            },

            setTheme(mode) {
                this.themeMode = mode;
                if (mode === 'system') {
                    localStorage.removeItem('zpm_theme');
                } else {
                    localStorage.setItem('zpm_theme', mode);
                }
                this.applyTheme(mode);
                if (window.ZPM && window.ZPM.toast) {
                    window.ZPM.toast('info', `Theme set to ${mode.charAt(0).toUpperCase() + mode.slice(1)} Mode`, 2000);
                }
            },

            applyTheme(mode) {
                const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (mode === 'dark' || (mode === 'system' && systemPrefersDark)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        };
    }
    </script>
    @stack('scripts')
</body>
</html>
