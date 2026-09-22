@extends('layouts.base')

@section('title', 'System Pre-Flight Check — Installer')

@section('content')
<div class="max-w-2xl mx-auto bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="px-6 py-6 border-b border-slate-100 dark:border-slate-700">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Step 1: System Pre-Flight Check</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Verifying server environment, PHP extensions, and file system permissions.</p>
    </div>

    <div class="p-6 space-y-6">
        <!-- PHP Version -->
        <div>
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-2">PHP Version</h2>
            <div class="flex items-center justify-between p-3 rounded-lg border {{ $requirements['php']['satisfied'] ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800' }}">
                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    PHP Version (Required: &ge; {{ $requirements['php']['required'] }})
                </span>
                <span class="text-xs font-bold px-2 py-1 rounded {{ $requirements['php']['satisfied'] ? 'bg-emerald-200 text-emerald-800 dark:bg-emerald-800 dark:text-emerald-100' : 'bg-rose-200 text-rose-800 dark:bg-rose-800 dark:text-rose-100' }}">
                    {{ $requirements['php']['version'] }} {{ $requirements['php']['satisfied'] ? '✓' : '✗' }}
                </span>
            </div>
        </div>

        <!-- PHP Extensions -->
        <div>
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-2">Required PHP Extensions</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                @foreach($requirements['extensions'] as $ext => $info)
                <div class="flex items-center justify-between p-2.5 rounded border {{ $info['loaded'] ? 'bg-slate-50 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300' : 'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300' }}">
                    <span><code>{{ $ext }}</code> ({{ $info['name'] }})</span>
                    <span class="font-bold ml-2">{{ $info['loaded'] ? '✓' : '✗' }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Directory Permissions -->
        <div>
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-2">Writable Directories</h2>
            <div class="space-y-1.5 text-xs">
                @foreach($requirements['permissions'] as $path => $info)
                <div class="flex items-center justify-between p-2.5 rounded border {{ $info['writable'] ? 'bg-slate-50 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300' : 'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300' }}">
                    <span><code>{{ $path }}</code></span>
                    <span class="font-bold {{ $info['writable'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        {{ $info['writable'] ? 'Writable ✓' : 'Not Writable ✗' }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700 flex justify-between items-center">
        <a href="{{ route('installer.welcome') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
            &larr; Back
        </a>

        @if($requirements['allSatisfied'])
        <a href="{{ route('installer.database') }}" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold shadow-sm transition">
            Continue to Database Setup &rarr;
        </a>
        @else
        <button disabled class="inline-flex items-center px-5 py-2.5 rounded-lg bg-slate-300 dark:bg-slate-700 text-slate-500 text-sm font-semibold cursor-not-allowed">
            Resolve Requirements to Proceed
        </button>
        @endif
    </div>
</div>
@endsection
