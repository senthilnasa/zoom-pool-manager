@extends('layouts.base')

@section('title', 'System Health & Diagnostics')

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Operations & System Health</h1>
            <p class="text-sm text-gray-500 mt-1">Real-time status of critical infrastructure, integrations, and scheduler.</p>
        </div>
        <div class="flex items-center space-x-3">
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider {{ $health['status'] === 'healthy' ? 'bg-green-100 text-green-800' : ($health['status'] === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                Status: {{ ucfirst($health['status']) }}
            </span>
            <a href="{{ route('admin.alerts') }}" class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Alerts
                @if($openAlertsCount > 0)
                    <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-red-600 text-white">
                        {{ $openAlertsCount }}
                    </span>
                @endif
            </a>
            <a href="{{ route('admin.backups') }}" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Backups
            </a>
            <a href="{{ route('admin.emergency') }}" class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100">
                Emergency Panel
            </a>
        </div>
    </div>

    <!-- Health Checks Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- 1. Database -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                    <span class="w-2.5 h-2.5 rounded-full {{ $health['checks']['database']['status'] === 'ok' ? 'bg-green-500' : 'bg-red-500' }} mr-2"></span>
                    Database
                </h3>
                <form method="POST" action="{{ route('admin.health.test', 'database') }}">
                    @csrf
                    <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Test</button>
                </form>
            </div>
            <p class="text-xs text-gray-500">Driver: <span class="font-mono text-gray-800">{{ $health['checks']['database']['driver'] }}</span></p>
            <p class="text-xs text-gray-500 mt-1">Version: <span class="text-gray-800 font-mono">{{ $health['checks']['database']['version'] }}</span></p>
        </div>

        <!-- 2. Queue Backlog -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                    <span class="w-2.5 h-2.5 rounded-full {{ $health['checks']['queue']['status'] === 'ok' ? 'bg-green-500' : 'bg-yellow-500' }} mr-2"></span>
                    Queue Worker
                </h3>
                <form method="POST" action="{{ route('admin.health.test', 'queue') }}">
                    @csrf
                    <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Test Push</button>
                </form>
            </div>
            <p class="text-xs text-gray-500">Pending Jobs: <span class="font-semibold text-gray-800">{{ $health['checks']['queue']['pending_jobs'] }}</span></p>
            <p class="text-xs text-gray-500 mt-1">Failed Jobs: <span class="font-semibold {{ $health['checks']['queue']['failed_jobs'] > 0 ? 'text-red-600' : 'text-gray-800' }}">{{ $health['checks']['queue']['failed_jobs'] }}</span></p>
        </div>

        <!-- 3. Scheduler -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                    <span class="w-2.5 h-2.5 rounded-full {{ $health['checks']['scheduler']['status'] === 'ok' ? 'bg-green-500' : 'bg-yellow-500' }} mr-2"></span>
                    Cron / Scheduler
                </h3>
            </div>
            <p class="text-xs text-gray-500">Last Heartbeat: <span class="text-gray-800 font-medium">{{ $health['checks']['scheduler']['last_heartbeat_at'] ?: 'Never / Inactive' }}</span></p>
            <p class="text-xs text-gray-400 mt-1">Runs every minute to trigger reminders and syncs.</p>
        </div>

        <!-- 4. Zoom Connections -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                    <span class="w-2.5 h-2.5 rounded-full {{ $health['checks']['zoom_connections']['status'] === 'ok' ? 'bg-green-500' : 'bg-yellow-500' }} mr-2"></span>
                    Zoom Connections
                </h3>
            </div>
            <p class="text-xs text-gray-500">Active Accounts: <span class="font-semibold text-gray-800">{{ count($health['checks']['zoom_connections']['connections']) }}</span></p>
            <p class="text-xs text-gray-400 mt-1">Server-to-Server OAuth status.</p>
        </div>

        <!-- 5. Inbound Webhooks -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500 mr-2"></span>
                    Inbound Webhooks
                </h3>
                <a href="{{ route('webhooks.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Log</a>
            </div>
            <p class="text-xs text-gray-500">Last Received: <span class="text-gray-800 font-medium">{{ $health['checks']['webhooks']['last_received_at'] ? Carbon\Carbon::parse($health['checks']['webhooks']['last_received_at'])->diffForHumans() : 'No events yet' }}</span></p>
            <p class="text-xs text-gray-500 mt-1">Total Processed: <span class="text-gray-800 font-semibold">{{ $health['checks']['webhooks']['total_events'] }}</span></p>
        </div>

        <!-- 6. Storage & Disk -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                    <span class="w-2.5 h-2.5 rounded-full {{ $health['checks']['storage']['status'] === 'ok' ? 'bg-green-500' : 'bg-red-500' }} mr-2"></span>
                    Storage & Disk
                </h3>
            </div>
            <p class="text-xs text-gray-500">Framework Writable: <span class="text-green-600 font-semibold">Yes</span></p>
            <p class="text-xs text-gray-500 mt-1">Free Space: <span class="text-gray-800 font-semibold">{{ number_format($health['checks']['storage']['free_space_bytes'] / 1073741824, 2) }} GB</span></p>
        </div>
    </div>
</div>
@endsection
