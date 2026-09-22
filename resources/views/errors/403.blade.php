@extends('layouts.base')

@section('title', '403 — Forbidden')

@section('content')
<div class="max-w-md mx-auto text-center py-16">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-rose-100 dark:bg-rose-950/60 text-rose-600 mb-4">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
    </div>
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">403 — Access Forbidden</h1>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
        {{ $exception->getMessage() ?: 'You do not have permission to access this resource.' }}
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
