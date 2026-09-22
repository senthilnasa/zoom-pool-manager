@extends('layouts.base')

@section('title', '404 — Page Not Found')

@section('content')
<div class="max-w-md mx-auto text-center py-16">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 mb-4">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">404 — Page Not Found</h1>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
        The requested page or meeting resource could not be found.
    </p>
    <div class="mt-4 text-xs font-mono text-slate-400">
        Reference ID: {{ (string) \Illuminate\Support\Str::uuid() }}
    </div>
    <div class="mt-6">
        <a href="{{ url('/') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-sm transition">
            &larr; Return Home
        </a>
    </div>
</div>
@endsection
