@extends('layouts.base')

@section('title', 'Welcome to Zoom Pool Manager Setup')

@section('content')
<div class="max-w-2xl mx-auto bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <!-- Header -->
    <div class="px-6 py-8 border-b border-slate-100 dark:border-slate-700 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-sky-100 dark:bg-sky-900/50 text-sky-600 dark:text-sky-400 mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Welcome to Zoom Pool Manager</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Automate your organization's Zoom resource pool allocation efficiently and securely.</p>
    </div>

    <!-- Steps Overview -->
    <div class="px-6 py-6 space-y-4">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Setup Process</h2>
        
        <ul class="space-y-3">
            <li class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-sky-50 dark:bg-sky-950 border border-sky-300 dark:border-sky-700 text-sky-700 dark:text-sky-300 text-xs font-bold flex items-center justify-center mr-3 mt-0.5">1</span>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">System Pre-Flight Check</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Verify PHP 8.3+, database extensions, and writable folders.</p>
                </div>
            </li>
            <li class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-500 text-xs font-bold flex items-center justify-center mr-3 mt-0.5">2</span>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Database Configuration</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Connect to MySQL 8.0+ or MariaDB 10.6+ and run initial migrations.</p>
                </div>
            </li>
            <li class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-500 text-xs font-bold flex items-center justify-center mr-3 mt-0.5">3</span>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Super Admin & Mandatory MFA</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Enroll the break-glass Super Administrator with TOTP authenticator.</p>
                </div>
            </li>
            <li class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-500 text-xs font-bold flex items-center justify-center mr-3 mt-0.5">4</span>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Organization Settings</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Configure institution name, default timezone, and meeting buffer.</p>
                </div>
            </li>
        </ul>

        <div class="mt-6 p-4 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300">
            <strong>Important Security Notice:</strong> Once setup is complete, the installer will be locked with a lock file. To unlock it later, CLI access is required: <code>php artisan zpm:installer:unlock</code>.
        </div>
    </div>

    <!-- Actions -->
    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700 flex justify-end">
        <a href="{{ route('installer.requirements') }}" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold shadow-sm transition">
            Begin Pre-Flight Check &rarr;
        </a>
    </div>
</div>
@endsection
