@extends('layouts.base')

@section('title', 'Series Details — Zoom Pool Manager')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Recurring Series</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $series->status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-rose-100 text-rose-800' }}">
                    {{ ucfirst($series->status) }}
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">ID: {{ $series->public_id }} &bull; Created by {{ $series->requester?->name ?? 'System' }}</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('series.index') }}" class="inline-flex items-center px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 transition">
                &larr; All Series
            </a>
            @if ($series->status === 'active')
                <button type="button" onclick="document.getElementById('cancel-series-modal').classList.remove('hidden')" class="inline-flex items-center px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-xl text-sm transition">
                    Cancel Series
                </button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <!-- Metadata Card -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900 dark:text-white mb-4">Series Overview</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold block">Recurrence Pattern</span>
                <code class="text-xs font-mono font-bold text-sky-600 dark:text-sky-400 mt-1 block">{{ $series->rrule }}</code>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold block">Allocation Strategy</span>
                <span class="font-medium text-slate-900 dark:text-white mt-1 block">{{ str_replace('_', ' ', $series->series_mode) }}</span>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold block">Primary Resource</span>
                <span class="font-medium text-slate-900 dark:text-white mt-1 block">{{ $series->zoomResource?->name ?? 'Dynamic (Per-occurrence pool)' }}</span>
            </div>
        </div>
    </div>

    <!-- Occurrences List -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Scheduled Occurrences ({{ $series->meetings->count() }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3">#</th>
                        <th class="px-6 py-3">Occurrence Title</th>
                        <th class="px-6 py-3">Date & Time</th>
                        <th class="px-6 py-3">Assigned Resource</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($series->meetings as $meeting)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-750 transition">
                            <td class="px-6 py-4 font-bold text-slate-400 text-xs">
                                #{{ $meeting->occurrence_index }}
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-900 dark:text-white">
                                <a href="{{ route('meetings.show', $meeting->public_id) }}" class="hover:underline hover:text-sky-600">
                                    {{ $meeting->title }}
                                </a>
                                @if ($meeting->is_detached_from_series)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                        Detached
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600 dark:text-slate-300">
                                {{ $meeting->starts_at->format('M j, Y — g:i A') }}
                            </td>
                            <td class="px-6 py-4 text-xs font-medium text-slate-700 dark:text-slate-300">
                                {{ $meeting->zoomResource?->name ?? 'Unassigned' }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $meeting->status === 'cancelled' ? 'bg-rose-100 text-rose-800' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300' }}">
                                    {{ ucfirst($meeting->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('meetings.show', $meeting->public_id) }}" class="text-xs text-sky-600 hover:underline">
                                    View
                                </a>
                                @if (! $meeting->is_detached_from_series && ! in_array($meeting->status, ['cancelled', 'ended', 'completed']))
                                    <form action="{{ route('series.detach-occurrence', ['seriesPublicId' => $series->public_id, 'meetingPublicId' => $meeting->public_id]) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" onclick="return confirm('Detach this occurrence? It will become an independent meeting that can be rescheduled or modified without affecting the rest of the series.')" class="text-xs text-amber-600 hover:underline">
                                            Detach
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">No occurrences found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Cancel Series Modal -->
<div id="cancel-series-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200 dark:border-slate-700">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Cancel Entire Series</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Cancelling this series will immediately cancel all future unstarted occurrences and release their held Zoom resources back into the organization's pool.
        </p>

        <form action="{{ route('series.cancel', $series->public_id) }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="cancel_reason" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Reason for Cancellation</label>
                <textarea id="cancel_reason" name="reason" rows="3" required placeholder="Provide cancellation reason..." class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-white focus:ring-rose-500 focus:border-rose-500"></textarea>
            </div>
            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="document.getElementById('cancel-series-modal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition">
                    Keep Series
                </button>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-sm font-semibold transition">
                    Cancel Series
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
