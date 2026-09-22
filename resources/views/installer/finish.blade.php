@extends('layouts.base')

@section('title', 'Installation Complete — Zoom Pool Manager')

@section('content')
<div class="max-w-2xl mx-auto bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden text-center p-8">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 mb-4">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
    </div>

    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Installation Successful!</h1>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
        Zoom Pool Manager is installed, configured, and protected against unauthorized re-installation.
    </p>

    <div class="mt-6 p-4 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-left text-xs space-y-2">
        <div class="flex items-center justify-between">
            <span class="text-slate-500">Lock File Status:</span>
            <span class="font-bold text-emerald-600 dark:text-emerald-400">Locked (storage/installed.lock) ✓</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-slate-500">Super Admin Status:</span>
            <span class="font-bold text-slate-700 dark:text-slate-300">Enrolled with Mandatory TOTP ✓</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-slate-500">Database Engine:</span>
            <span class="font-bold text-slate-700 dark:text-slate-300">Migrations Complete ✓</span>
        </div>
    </div>

    <div class="mt-8 flex justify-center space-x-4">
        <a href="{{ url('/') }}" class="inline-flex items-center px-6 py-3 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold text-sm shadow-sm transition">
            Proceed to Dashboard &rarr;
        </a>
    </div>
</div>
@endsection
