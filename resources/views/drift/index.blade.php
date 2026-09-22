@extends('layouts.base')

@section('title', 'Drift Reconciliation')

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Drift Reconciliation</h1>
            <p class="text-sm text-gray-500 mt-1">Detect discrepancies between ZPM allocations and actual Zoom state.</p>
        </div>
        <form method="POST" action="{{ route('drift.scan') }}">
            @csrf
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Run Reconciliation Scan
            </button>
        </form>
    </div>

    <!-- Status Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            <a href="{{ route('drift.index', ['status' => 'open']) }}" class="{{ $status === 'open' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Open Conflicts
            </a>
            <a href="{{ route('drift.index', ['status' => 'resolved']) }}" class="{{ $status === 'resolved' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Resolved
            </a>
            <a href="{{ route('drift.index', ['status' => 'all']) }}" class="{{ $status === 'all' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                All Conflicts
            </a>
        </nav>
    </div>

    <!-- Conflicts List -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
        @if($conflicts->count() > 0)
            <ul class="divide-y divide-gray-200">
                @foreach($conflicts as $conflict)
                    <li class="p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $conflict->status === 'open' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ strtoupper($conflict->incident_type) }}
                                </span>
                                <span class="text-xs text-gray-500 font-mono">ID: {{ $conflict->public_id }}</span>
                                <span class="text-xs text-gray-400">&bull; Detected: {{ $conflict->created_at->diffForHumans() }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-medium px-2 py-0.5 rounded {{ $conflict->status === 'open' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                    Status: {{ ucfirst($conflict->status) }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-md text-sm">
                            <div>
                                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Conflict Details</h2>
                                <p class="text-gray-800 font-medium">
                                    Resource: {{ $conflict->resource ? $conflict->resource->name : 'N/A' }}
                                </p>
                                @if($conflict->meeting)
                                    <p class="text-gray-600 text-xs mt-1">
                                        Meeting: <a href="{{ route('meetings.show', $conflict->meeting->public_id) }}" class="text-blue-600 hover:underline">{{ $conflict->meeting->title }}</a>
                                    </p>
                                @endif
                                @if($conflict->zoom_meeting_id)
                                    <p class="text-gray-600 text-xs mt-0.5">Zoom Meeting ID: <span class="font-mono">{{ $conflict->zoom_meeting_id }}</span></p>
                                @endif
                            </div>
                            <div>
                                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Diagnostic Payload</h2>
                                <pre class="text-xs bg-white p-2 rounded border border-gray-200 overflow-x-auto text-gray-700 font-mono">{{ json_encode($conflict->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        </div>

                        @if($conflict->status === 'open')
                            <div class="mt-4 border-t border-gray-100 pt-4 flex flex-wrap gap-2 justify-end">
                                <!-- Action: Resolve (Re-apply ZPM) -->
                                <form method="POST" action="{{ route('drift.resolve', $conflict->public_id) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="resolve">
                                    <button type="submit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded shadow-sm">
                                        Resolve (Re-apply ZPM)
                                    </button>
                                </form>

                                <!-- Action: Accept Zoom Value -->
                                <form method="POST" action="{{ route('drift.resolve', $conflict->public_id) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="accepted_zoom">
                                    <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded shadow-sm">
                                        Accept Zoom Value
                                    </button>
                                </form>

                                <!-- Action: Mark External (Block allocator) -->
                                @if($conflict->incident_type === 'unmanaged_external_meeting')
                                    <form method="POST" action="{{ route('drift.resolve', $conflict->public_id) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="marked_external">
                                        <button type="submit" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded shadow-sm">
                                            Mark External Reservation
                                        </button>
                                    </form>
                                @endif

                                <!-- Action: Ignore -->
                                <form method="POST" action="{{ route('drift.resolve', $conflict->public_id) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="ignored">
                                    <button type="submit" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-medium rounded shadow-sm">
                                        Ignore Conflict
                                    </button>
                                </form>
                            </div>
                        @else
                            <div class="mt-3 text-xs text-gray-500">
                                Resolved by: {{ $conflict->resolvedBy ? $conflict->resolvedBy->name : 'System' }} on {{ $conflict->resolved_at?->format('M d, Y h:i A') }}
                                @if($conflict->resolution_notes)
                                    &bull; Notes: {{ $conflict->resolution_notes }}
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="p-4 border-t border-gray-200">
                {{ $conflicts->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No {{ $status }} drift conflicts</h3>
                <p class="mt-1 text-sm text-gray-500">Your Zoom Pool reservations and Zoom account state are in perfect alignment.</p>
            </div>
        @endif
    </div>
</div>
@endsection
