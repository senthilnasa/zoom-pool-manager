@extends('layouts.base')

@section('title', config('app.name', 'Zoom Pool Manager'))

@section('content')
<div class="max-w-3xl mx-auto text-center py-12">
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-sky-600 text-white font-bold text-3xl shadow-lg mb-6">
        Z
    </div>
    <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight sm:text-4xl">
        {{ config('app.name', 'Zoom Pool Manager') }}
    </h1>
    <p class="mt-4 text-base text-slate-600 dark:text-slate-300 max-w-xl mx-auto">
        Automate your Zoom resource pool allocation, secure host controls, and optimize license utilization across the organization.
    </p>

    <div class="mt-8 flex justify-center gap-4">
        <a href="{{ route('admin.health') }}" class="px-5 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm transition">
            System Health API
        </a>
    </div>
</div>
@endsection
