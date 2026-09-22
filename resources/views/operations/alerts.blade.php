@extends('layouts.base')

@section('title', 'Operations Alerts')

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Operational Anomaly Alerts</h1>
            <p class="text-sm text-gray-500 mt-1">Automated infrastructure anomaly detection with deduplication and throttle protections.</p>
        </div>
        <a href="{{ route('admin.health') }}" class="text-sm font-medium text-blue-600 hover:text-blue-500">
            &larr; Back to Health Dashboard
        </a>
    </div>

    <!-- Status Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            <a href="{{ route('admin.alerts', ['status' => 'open']) }}" class="{{ $status === 'open' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Open Alerts
            </a>
            <a href="{{ route('admin.alerts', ['status' => 'resolved']) }}" class="{{ $status === 'resolved' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Resolved
            </a>
            <a href="{{ route('admin.alerts', ['status' => 'all']) }}" class="{{ $status === 'all' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                All Alerts
            </a>
        </nav>
    </div>

    <!-- Alerts List -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
        @if($alerts->count() > 0)
            <ul class="divide-y divide-gray-200">
                @foreach($alerts as $alert)
                    <li class="p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <span class="px-2.5 py-1 text-xs font-bold rounded-full {{ $alert->severity === 'critical' ? 'bg-red-100 text-red-800' : ($alert->severity === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800') }}">
                                    {{ strtoupper($alert->severity) }}
                                </span>
                                <h3 class="text-base font-semibold text-gray-900">{{ $alert->title }}</h3>
                                <span class="text-xs text-gray-400 font-mono">{{ $alert->key }}</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="text-xs text-gray-500">Occurrences: <strong class="text-gray-800">{{ $alert->count }}</strong></span>
                                @if(! $alert->isResolved())
                                    <form method="POST" action="{{ route('admin.alerts.resolve', $alert->public_id) }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded shadow-sm">
                                            Mark Resolved
                                        </button>
                                    </form>
                                @else
                                    <span class="px-2.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                        Resolved {{ $alert->resolved_at?->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">{{ $alert->message }}</p>
                        <div class="mt-2 text-xs text-gray-400">
                            First seen: {{ $alert->first_seen_at->format('M d, Y H:i:s') }} &bull; Last seen: {{ $alert->last_seen_at->diffForHumans() }}
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="p-4 border-t border-gray-200">
                {{ $alerts->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No {{ $status }} alerts</h3>
                <p class="mt-1 text-sm text-gray-500">System infrastructure is functioning within normal thresholds.</p>
            </div>
        @endif
    </div>
</div>
@endsection
