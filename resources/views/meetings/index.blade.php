@extends('layouts.base')

@section('title', 'Meetings — ' . config('app.name', 'Zoom Pool Manager'))
@section('page_title', 'Scheduled Meetings')

@section('content')
<div class="space-y-6">

    <!-- Top Action Ribbon -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-white flex items-center space-x-2">
                <span>Managed Meetings</span>
                <span class="px-2 py-0.5 text-xs font-semibold rounded-lg bg-sky-500/10 border border-sky-500/30 text-sky-400">
                    Pooled Resources
                </span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">Manage scheduled, pending, active, and completed Zoom sessions.</p>
        </div>

        <div class="flex items-center space-x-3">
            <a href="{{ route('meetings.create') }}" class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-semibold text-xs shadow-glow transition duration-200 flex items-center space-x-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Book Meeting</span>
            </a>
            <a href="{{ route('calendar') }}" class="px-3.5 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-200 border border-white/10 text-xs font-medium transition shadow-sm flex items-center space-x-1.5">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Calendar View</span>
            </a>
        </div>
    </div>

    <!-- Live Search & Filter Bar -->
    <div class="p-4 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/5 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-glass">
        <div class="relative w-full sm:w-80">
            <svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text"
                   id="meeting-search"
                   placeholder="Instant search by title, organizer, or status..."
                   class="w-full pl-9 pr-3.5 py-2 bg-slate-950/60 border border-white/10 rounded-xl text-xs text-white placeholder-slate-400 focus:ring-2 focus:ring-sky-500 focus:outline-none transition">
        </div>
        <div class="text-xs text-slate-400 flex items-center space-x-1">
            <span>Showing</span>
            <span class="font-semibold text-white">{{ $meetings->count() }}</span>
            <span>meetings</span>
        </div>
    </div>

    <!-- Meetings Table Container -->
    <div class="rounded-3xl bg-slate-900/60 backdrop-blur-xl border border-white/5 overflow-hidden shadow-glass">
        <div class="overflow-x-auto">
            <table id="meetings-table" class="min-w-full divide-y divide-white/5 text-left text-xs">
                <thead class="bg-slate-950/60 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Title & Details</th>
                        <th class="px-6 py-4">Schedule Window</th>
                        <th class="px-6 py-4">Allocated Resource</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-slate-300">
                    @forelse ($meetings as $m)
                        <tr class="hover:bg-slate-800/40 transition duration-150">
                            <td class="px-6 py-4">
                                <a href="{{ route('meetings.show', $m->public_id) }}" class="font-semibold text-white hover:text-sky-400 transition text-sm">
                                    {{ $m->title }}
                                </a>
                                <div class="text-[11px] text-slate-400 mt-0.5 flex items-center space-x-1.5">
                                    <span class="capitalize">{{ $m->meeting_type }}</span>
                                    <span>&bull;</span>
                                    <span>{{ $m->participant_count }} participants</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-white font-medium">{{ $m->starts_at->format('M d, Y') }}</div>
                                <div class="text-[11px] text-slate-400">{{ $m->starts_at->format('H:i') }} – {{ $m->ends_at->format('H:i') }} ({{ $m->timezone }})</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($m->zoomResource)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[11px] font-semibold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                                        Resource #{{ $m->zoomResource->id }}
                                    </span>
                                @else
                                    <span class="text-slate-400 text-xs italic">Unallocated</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[11px] font-bold tracking-wide
                                    @if ($m->status === 'scheduled') bg-emerald-500/10 text-emerald-400 border border-emerald-500/20
                                    @elseif ($m->status === 'allocating') bg-sky-500/10 text-sky-400 border border-sky-500/20
                                    @elseif ($m->status === 'pending_approval') bg-amber-500/10 text-amber-400 border border-amber-500/20
                                    @elseif ($m->status === 'cancelled') bg-slate-800 text-slate-400 border border-white/5
                                    @else bg-rose-500/10 text-rose-400 border border-rose-500/20
                                    @endif">
                                    {{ ucfirst(str_replace('_', ' ', $m->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <a href="{{ route('meetings.show', $m->public_id) }}" class="inline-flex items-center space-x-1 px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-slate-200 hover:text-white transition border border-white/5 text-xs font-medium">
                                    <span>Details</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                                <p class="text-sm">No meetings found.</p>
                                <a href="{{ route('meetings.create') }}" class="mt-2 inline-block text-xs text-sky-400 hover:underline">Schedule your first pooled meeting →</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.ZPM && window.ZPM.filterTable) {
        window.ZPM.filterTable('#meeting-search', '#meetings-table');
    }
});
</script>
@endpush
@endsection
