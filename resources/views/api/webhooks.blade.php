@extends('layouts.base')

@section('title', 'Outbound Webhooks')

@section('content')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Outbound Webhooks</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Dispatch real-time signed HTTP notifications to your internal systems when meetings are scheduled, started, or ended.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            <a href="{{ route('admin.api-keys.index') }}" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700">
                API Keys
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Add Subscription Form -->
        <div class="lg:col-span-1 bg-white dark:bg-slate-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Add Webhook Endpoint</h2>
            <form action="{{ route('admin.outbound-webhooks.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Endpoint Name</label>
                    <input type="text" name="name" required placeholder="e.g. ERP Attendance Webhook" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm shadow-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Payload URL</label>
                    <input type="url" name="url" required placeholder="https://api.example.com/webhooks/zpm" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm shadow-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Secret Token (for HMAC-SHA256)</label>
                    <input type="text" name="secret" required placeholder="Min 16 characters" minlength="16" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm shadow-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Subscribed Events</label>
                    <div class="space-y-2 text-sm">
                        <label class="flex items-center">
                            <input type="checkbox" name="events[]" value="meeting.created" checked class="rounded border-slate-300 text-indigo-600 shadow-sm" />
                            <span class="ml-2 text-slate-700 dark:text-slate-300">meeting.created</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="events[]" value="meeting.cancelled" checked class="rounded border-slate-300 text-indigo-600 shadow-sm" />
                            <span class="ml-2 text-slate-700 dark:text-slate-300">meeting.cancelled</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="events[]" value="meeting.started" checked class="rounded border-slate-300 text-indigo-600 shadow-sm" />
                            <span class="ml-2 text-slate-700 dark:text-slate-300">meeting.started</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="events[]" value="meeting.ended" checked class="rounded border-slate-300 text-indigo-600 shadow-sm" />
                            <span class="ml-2 text-slate-700 dark:text-slate-300">meeting.ended</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="events[]" value="recording.completed" class="rounded border-slate-300 text-indigo-600 shadow-sm" />
                            <span class="ml-2 text-slate-700 dark:text-slate-300">recording.completed</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none">
                    Register Webhook
                </button>
            </form>
        </div>

        <!-- Subscriptions & Delivery Log -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow overflow-hidden">
                <div class="p-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Active Subscriptions</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Name & URL</th>
                                <th class="px-4 py-3 text-left font-medium">Events</th>
                                <th class="px-4 py-3 text-left font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @forelse ($subscriptions as $sub)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ $sub->name }}</div>
                                        <div class="text-xs text-slate-500 font-mono truncate max-w-xs">{{ $sub->url }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($sub->events ?? [] as $evt)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200">
                                                    {{ $evt }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($sub->is_active)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-300">
                                                Disabled
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <form action="{{ route('admin.outbound-webhooks.test', $sub->public_id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                                Ping
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.outbound-webhooks.toggle', $sub->public_id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-slate-600 dark:text-slate-400 hover:underline">
                                                {{ $sub->is_active ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-slate-500">
                                        No outbound webhooks configured.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Deliveries -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow overflow-hidden">
                <div class="p-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Recent Delivery Logs</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Event</th>
                                <th class="px-4 py-3 text-left font-medium">Endpoint</th>
                                <th class="px-4 py-3 text-left font-medium">Response</th>
                                <th class="px-4 py-3 text-left font-medium">Time</th>
                                <th class="px-4 py-3 text-right font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @forelse ($deliveries as $del)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-900 dark:text-white">
                                        {{ $del->event_type }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500 truncate max-w-xs">
                                        {{ $del->subscription?->name ?? 'Deleted' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($del->status === 'delivered')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">
                                                HTTP {{ $del->response_status }}
                                            </span>
                                        @elseif ($del->status === 'pending')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                                Pending (Attempt {{ $del->attempt }})
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800">
                                                Failed ({{ $del->response_status ?: 'Err' }})
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500">
                                        {{ $del->created_at?->diffForHumans() }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($del->status !== 'delivered')
                                            <form action="{{ route('admin.outbound-webhooks.retry', $del->public_id) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs font-medium text-indigo-600 hover:underline">
                                                    Retry
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-slate-500">
                                        No recent delivery logs.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
