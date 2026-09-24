@extends('layouts.base')

@section('title', 'Break-Glass Local Sign In — Zoom Pool Manager')

@section('content')
<div class="max-w-md mx-auto my-8">
    <div class="bg-white dark:bg-slate-800 shadow-xl rounded-2xl border border-amber-200 dark:border-amber-800/60 overflow-hidden">
        <!-- Security Header Banner -->
        <div class="bg-amber-500/10 border-b border-amber-200 dark:border-amber-800/60 px-6 py-3 flex items-center space-x-2 text-amber-800 dark:text-amber-300 text-xs font-semibold">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span>Break-Glass Administrator Emergency Access</span>
        </div>

        <div class="px-8 pt-6 pb-6 text-center">
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Local Authentication</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Notice: All login attempts and IP addresses are recorded to immutable audit trails.
            </p>
        </div>

        <div class="px-8 pb-8 space-y-6">
            @if ($errors->any())
                <div class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl text-sm text-rose-700 dark:text-rose-400">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Administrator Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                           class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:outline-none sm:text-sm">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
                    <input type="password" name="password" id="password" required
                           class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:outline-none sm:text-sm">
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center text-xs text-slate-600 dark:text-slate-400 cursor-pointer">
                        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 dark:border-slate-600 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50 mr-2">
                        <span>Remember this device (Auto Sign-In)</span>
                    </label>
                </div>

                <button type="submit"
                        class="w-full py-2.5 px-4 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-xl shadow-sm transition">
                    Authenticate Break-Glass Account
                </button>
            </form>

            <div class="pt-2 text-center">
                <a href="{{ route('login') }}" class="text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    &larr; Return to regular sign in
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
