@extends('layouts.base')

@section('title', '500 — Server Error')

@section('content')
<div class="max-w-md mx-auto text-center py-16">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-rose-100 dark:bg-rose-950/60 text-rose-600 mb-4">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
    </div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">500 — Internal Server Error</h1>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
        An unexpected error occurred while processing your request. Please contact your system administrator.
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
