@extends('layouts.base')

@section('title', 'Resource Timeline Calendar — Zoom Pool Manager')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Resource Timeline Calendar</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Live occupancy, buffer windows, and active pooled allocations.</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('meetings.index') }}" class="inline-flex items-center px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 transition">
                &larr; My Meetings
            </a>
            <a href="{{ route('meetings.create') }}" class="inline-flex items-center px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-xl text-sm shadow-sm transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Book Meeting
            </a>
        </div>
    </div>

    <!-- Resource Grid Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($resources as $resource)
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white">{{ $resource->name }}</h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $resource->zoomUser?->email ?? 'No Zoom User' }}</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $resource->status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">
                        {{ ucfirst($resource->status) }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs text-slate-500 dark:text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                    <div>Capacity: <strong class="text-slate-700 dark:text-slate-200">{{ $resource->capacity }}</strong></div>
                    <div>Pool: <strong class="text-slate-700 dark:text-slate-200">{{ $resource->primaryPool?->name ?? 'None' }}</strong></div>
                    <div>Health: <strong class="text-emerald-600 dark:text-emerald-400 capitalize">{{ $resource->health_status }}</strong></div>
                    <div>Type: <strong class="text-slate-700 dark:text-slate-200">{{ strtoupper($resource->zoomUser?->type ?? 'USER') }}</strong></div>
                </div>

                <!-- Upcoming Occupancy list -->
                <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Today's Occupancy</div>
                    @php
                        $reservations = \App\Domain\Scheduling\Models\ResourceReservation::where('resource_id', $resource->id)
                            ->where('occupied_until', '>=', now()->startOfDay())
                            ->where('occupied_from', '<=', now()->endOfDay())
                            ->whereIn('status', ['confirmed', 'held'])
                            ->orderBy('occupied_from')
                            ->take(3)
                            ->get();
                    @endphp

                    @forelse ($reservations as $res)
                        <div class="p-2 rounded-lg bg-slate-50 dark:bg-slate-900/50 text-xs flex items-center justify-between border border-slate-200 dark:border-slate-700">
                            <div>
                                <span class="font-medium text-slate-900 dark:text-white">
                                    {{ $res->occupied_from->format('H:i') }} - {{ $res->occupied_until->format('H:i') }}
                                </span>
                                <span class="text-slate-400 block text-[10px] capitalize">{{ $res->reservation_type }} ({{ $res->status }})</span>
                            </div>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $res->status === 'confirmed' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' }}">
                                {{ $res->status }}
                            </span>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-2 italic text-center">
                            No reservations for today.
                        </div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="col-span-3 text-center py-12 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700">
                <p class="text-slate-500 dark:text-slate-400">No managed Zoom resources found in pool.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
