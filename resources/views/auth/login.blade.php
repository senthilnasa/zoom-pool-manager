@extends('layouts.base')

@section('title', 'Sign In — Zoom Pool Manager')

@section('content')
<div class="max-w-md mx-auto my-8">
    <div class="bg-white dark:bg-slate-800 shadow-xl rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        @php
            $customLogo = \App\Domain\Settings\Models\Setting::get('org.logo_url');
            $orgName = \App\Domain\Settings\Models\Setting::get('org.name', config('app.name', 'Zoom Pool Manager'));
        @endphp
        <div class="px-8 pt-8 pb-6 text-center">
            @if(!empty($customLogo))
                <div class="flex items-center justify-center mb-4">
                    <img src="{{ $customLogo }}" alt="{{ $orgName }}" class="h-12 w-auto max-w-[200px] object-contain rounded-xl shadow-sm" />
                </div>
            @else
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-sky-100 dark:bg-sky-900/50 text-sky-600 dark:text-sky-400 mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                </div>
            @endif
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                @if(!empty($orgName) && $orgName !== 'Zoom Pool Manager')
                    Sign In to {{ $orgName }}
                    <span class="block text-xs font-normal text-slate-400 mt-0.5">Zoom Pool Manager (ZPM)</span>
                @else
                    Sign In to ZPM
                @endif
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Access pooled Zoom meeting resources and schedules</p>
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

            @if ($providers->isNotEmpty())
                <div class="space-y-3">
                    @foreach ($providers as $provider)
                        <a href="{{ route('auth.sso.redirect', ['provider' => $provider->public_id]) }}"
                           class="w-full flex items-center justify-center px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600 transition shadow-sm">
                            @if ($provider->driver === 'google')
                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                </svg>
                            @elseif ($provider->driver === 'microsoft' || $provider->driver === 'azure')
                                <svg class="w-5 h-5 mr-2" viewBox="0 0 23 23">
                                    <path fill="#f35325" d="M1 1h10v10H1z"/>
                                    <path fill="#81bc06" d="M12 1h10v10H12z"/>
                                    <path fill="#05a6f0" d="M1 12h10v10H1z"/>
                                    <path fill="#ffba08" d="M12 12h10v10H12z"/>
                                </svg>
                            @else
                                <svg class="w-5 h-5 mr-2 text-indigo-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                                </svg>
                            @endif
                            Sign in with {{ $provider->name }}
                        </a>
                    @endforeach
                </div>

                @if (! $ssoOnly)
                    <div class="relative my-4">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-slate-200 dark:border-slate-700"></div>
                        </div>
                        <div class="relative flex justify-center text-xs uppercase">
                            <span class="bg-white dark:bg-slate-800 px-2 text-slate-500">Or local credentials</span>
                        </div>
                    </div>
                @endif
            @endif

            @if (! $ssoOnly)
                <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email Address</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                               class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none sm:text-sm">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
                        <input type="password" name="password" id="password" required
                               class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none sm:text-sm">
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center text-xs text-slate-600 dark:text-slate-400 cursor-pointer">
                            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 dark:border-slate-600 text-sky-600 shadow-sm focus:border-sky-300 focus:ring focus:ring-sky-200 focus:ring-opacity-50 mr-2">
                            <span>Remember this device (Auto Sign-In)</span>
                        </label>
                    </div>

                    <button type="submit"
                            class="w-full py-2.5 px-4 bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-xl shadow-sm transition">
                        Sign In
                    </button>
                </form>
            @else
                <div class="pt-4 text-center border-t border-slate-100 dark:border-slate-700/60">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Break-glass administrator?
                        <a href="{{ route('login.local') }}" class="font-medium text-sky-600 dark:text-sky-400 hover:underline">
                            Use Break-Glass Login
                        </a>
                    </p>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-6 text-center text-xs text-slate-500 dark:text-slate-400 flex items-center justify-center gap-3">
        <a href="{{ route('legal.privacy') }}" class="hover:underline hover:text-slate-700 dark:hover:text-slate-300">Privacy Policy</a>
        <span>•</span>
        <a href="{{ route('legal.terms') }}" class="hover:underline hover:text-slate-700 dark:hover:text-slate-300">Terms of Service</a>
    </div>
</div>
@endsection
