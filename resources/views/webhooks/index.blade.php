@extends('layouts.base')

@section('title', 'Inbound Webhook Events')

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Inbound Zoom Webhooks</h1>
            <p class="text-sm text-gray-500 mt-1">Audit log and replay pipeline for incoming Zoom events.</p>
        </div>
    </div>

    <!-- Webhook Ingestion Log -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
        @if($events->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event ID / Nonce</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" x-data="{ expanded: null }">
                        @foreach($events as $event)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ $event->created_at->format('M d, Y H:i:s') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 font-mono text-xs">{{ $event->event_type }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 font-mono">{{ Str::limit($event->event_id, 32) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $event->status === 'processed' ? 'bg-green-100 text-green-800' : ($event->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                        {{ ucfirst($event->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ $event->attempts }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-xs space-x-2">
                                    <button type="button" @click="expanded = expanded === {{ $event->id }} ? null : {{ $event->id }}" class="text-blue-600 hover:text-blue-900 font-medium">
                                        Payload
                                    </button>
                                    <form method="POST" action="{{ route('webhooks.replay', $event->public_id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-amber-600 hover:text-amber-900 font-medium">Replay</button>
                                    </form>
                                </td>
                            </tr>
                            <tr x-show="expanded === {{ $event->id }}" class="bg-gray-50">
                                <td colspan="6" class="px-6 py-4">
                                    <h4 class="text-xs font-semibold text-gray-600 uppercase mb-1">Payload Details (IP: {{ $event->ip_address }})</h4>
                                    @if($event->error_message)
                                        <div class="mb-2 p-2 bg-red-50 text-red-700 text-xs rounded border border-red-200">
                                            <strong>Error:</strong> {{ $event->error_message }}
                                        </div>
                                    @endif
                                    <pre class="bg-white p-3 rounded border border-gray-200 text-xs font-mono overflow-x-auto text-gray-800 max-h-60">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-200">
                {{ $events->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No webhook events logged yet</h3>
                <p class="mt-1 text-sm text-gray-500">Incoming Zoom webhooks will be verified and captured here in real-time.</p>
            </div>
        @endif
    </div>
</div>
@endsection
