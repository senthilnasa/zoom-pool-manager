@extends('layouts.base')

@section('title', 'Recurring Series — Zoom Pool Manager')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Recurring Series</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Multi-session classes, seminars, and repeating pooled meetings.</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('series.create') }}" class="inline-flex items-center px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-xl text-sm shadow-sm transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Recurring Series
            </a>
            <a href="{{ route('meetings.index') }}" class="inline-flex items-center px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 transition">
                All Meetings
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">Series Title</th>
                        <th class="px-6 py-3.5">Schedule / RRULE</th>
                        <th class="px-6 py-3.5">Allocation Mode</th>
                        <th class="px-6 py-3.5">Occurrences</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($series as $s)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-750 transition">
                            <td class="px-6 py-4 font-semibold text-slate-900 dark:text-white">
                                <a href="{{ route('series.show', $s->public_id) }}" class="hover:text-sky-600 dark:hover:text-sky-400">
                                    {{ $s->meetings->first()?->title ?? 'Untitled Series' }}
                                </a>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-500 dark:text-slate-400">
                                {{ $s->rrule }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                    {{ str_replace('_', ' ', $s->series_mode) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                {{ $s->meetings_count }} sessions
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $s->status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-rose-100 text-rose-800' }}">
                                    {{ ucfirst($s->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('series.show', $s->public_id) }}" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                                    View Series &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                No recurring series found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($series->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-700">
                {{ $series->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
