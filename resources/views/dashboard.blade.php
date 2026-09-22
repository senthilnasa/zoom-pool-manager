@extends('layouts.base')

@section('title', 'Dashboard — ' . config('app.name', 'Zoom Pool Manager'))
@section('page_title', 'Workspace Dashboard')

@section('content')
<div class="space-y-6">

    <!-- Hero Glassmorphism Card -->
    <div class="p-6 sm:p-8 rounded-3xl bg-slate-900/60 backdrop-blur-2xl border border-white/5 shadow-glass relative overflow-hidden">
        <div class="absolute -right-16 -top-16 w-64 h-64 bg-sky-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <span class="px-2.5 py-1 text-[11px] font-semibold tracking-wide uppercase bg-sky-500/10 text-sky-400 border border-sky-500/20 rounded-lg">
                    Authenticated Workspace
                </span>
                <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight mt-2">
                    Welcome back, {{ $user->name }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1 flex items-center space-x-2">
                    <span>{{ $user->email }}</span>
                    @if ($user->department)
                        <span>•</span>
                        <span class="text-sky-400 font-medium">{{ $user->department->name }}</span>
                    @endif
                </p>
            </div>

            <div class="flex items-center space-x-3">
                <a href="{{ route('meetings.create') }}" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-semibold text-xs shadow-glow transition duration-200 flex items-center space-x-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Schedule Meeting</span>
                </a>
            </div>
        </div>

        <!-- Assigned Roles Pill Bar -->
        <div class="mt-6 pt-6 border-t border-white/5 flex flex-wrap items-center gap-2">
            <span class="text-[11px] font-medium text-slate-400 mr-1">Active Roles:</span>
            @forelse ($user->roles as $role)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    {{ ucwords(str_replace('_', ' ', $role->name)) }}
                </span>
            @empty
                <span class="text-xs text-slate-400">Standard User</span>
            @endforelse
        </div>
    </div>

    <!-- Quick Navigation Glass Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <a href="{{ route('meetings.index') }}" class="p-5 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/5 hover:border-sky-500/40 shadow-glass transition duration-200 group">
            <div class="w-9 h-9 rounded-xl bg-sky-500/10 text-sky-400 flex items-center justify-center border border-sky-500/20 group-hover:scale-110 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="text-sm font-bold text-white mt-3">Scheduled Meetings</h3>
            <p class="text-[11px] text-slate-400 mt-1">View reservations & host controls</p>
        </a>

        <a href="{{ route('approvals.index') }}" class="p-5 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/5 hover:border-indigo-500/40 shadow-glass transition duration-200 group">
            <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center border border-indigo-500/20 group-hover:scale-110 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-sm font-bold text-white mt-3">Approvals Hub</h3>
            <p class="text-[11px] text-slate-400 mt-1">Pending sign-offs and chains</p>
        </a>

        <a href="{{ route('recordings.index') }}" class="p-5 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/5 hover:border-purple-500/40 shadow-glass transition duration-200 group">
            <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center border border-purple-500/20 group-hover:scale-110 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>
            </div>
            <h3 class="text-sm font-bold text-white mt-3">Cloud Recordings</h3>
            <p class="text-[11px] text-slate-400 mt-1">Secure media stream access</p>
        </a>

        <a href="{{ route('admin.health') }}" class="p-5 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/5 hover:border-emerald-500/40 shadow-glass transition duration-200 group">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20 group-hover:scale-110 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h3 class="text-sm font-bold text-white mt-3">Operations & Health</h3>
            <p class="text-[11px] text-slate-400 mt-1">Real-time status diagnostics</p>
        </a>

    </div>

    <!-- Session Management Card -->
    <div class="p-6 rounded-3xl bg-slate-900/60 backdrop-blur-xl border border-white/5 shadow-glass">
        <h2 class="text-sm font-bold text-white">Security & Active Device Sessions</h2>
        <p class="text-xs text-slate-400 mt-1">
            Revoke all other active login sessions across remote devices and browsers.
        </p>

        @if ($errors->has('password'))
            <div class="mt-4 p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-xs text-rose-300">
                {{ $errors->first('password') }}
            </div>
        @endif

        <form action="{{ route('logout.other-devices') }}" method="POST" class="mt-4 flex flex-col sm:flex-row gap-3 max-w-md">
            @csrf
            <input type="password" name="password" placeholder="Confirm your password" required
                   class="flex-1 px-3.5 py-2.5 bg-slate-950/60 border border-white/10 rounded-xl text-xs text-white placeholder-slate-400 focus:ring-2 focus:ring-sky-500 focus:outline-none">
            <button type="submit" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-xl border border-white/10 transition shadow-sm whitespace-nowrap">
                Revoke Other Sessions
            </button>
        </form>
    </div>

</div>
@endsection
