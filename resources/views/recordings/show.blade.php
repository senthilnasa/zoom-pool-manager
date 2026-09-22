@extends('layouts.base')

@section('title', 'Recording Details')

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-4">
        <a href="{{ route('recordings.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-500 flex items-center">
            &larr; Back to Cloud Recordings
        </a>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg border border-gray-200 mb-6">
        <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold text-gray-900">{{ $recording->topic ?: 'Cloud Recording' }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Recorded on {{ $recording->recording_start ? $recording->recording_start->format('l, F j, Y \a\t g:i A') : 'N/A' }}
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $recording->status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                    {{ ucfirst($recording->status) }}
                </span>
                @if($recording->status === 'completed')
                    <a href="{{ route('recordings.play', $recording->public_id) }}" target="_blank" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                        Launch Playback
                    </a>
                @endif
            </div>
        </div>

        <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-gray-200">
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Duration</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $recording->duration_minutes }} minutes</dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Total File Size</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ number_format($recording->file_size_bytes / 1048576, 2) }} MB</dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Associated Meeting</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        @if($recording->meeting)
                            <a href="{{ route('meetings.show', $recording->meeting->public_id) }}" class="text-blue-600 hover:underline">
                                {{ $recording->meeting->title }} ({{ $recording->meeting->public_id }})
                            </a>
                        @else
                            <span class="text-gray-400">Unassociated / Standalone</span>
                        @endif
                    </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Logical Owner</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        {{ $recording->logicalOwner ? $recording->logicalOwner->name . ' (' . $recording->logicalOwner->email . ')' : 'System / Unassigned' }}
                    </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Host Resource</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        {{ $recording->resource ? $recording->resource->name : 'N/A' }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- Recording Media Files -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg border border-gray-200 mb-6">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-lg font-bold text-gray-900">Recorded Media Files</h2>
            <p class="mt-1 text-xs text-gray-500">Individual video, audio, and transcript files available for this session.</p>
        </div>
        <div class="border-t border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Extension</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($recording->files as $file)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">{{ $file->file_type }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500 uppercase">.{{ $file->file_extension }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ number_format($file->file_size_bytes / 1048576, 2) }} MB</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                    {{ ucfirst($file->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">No media files registered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Access Audit History (Admins or Owner) -->
    @if(Auth::user()->can('audit.view') || Auth::id() === $recording->logical_owner_user_id)
        <div class="bg-white shadow overflow-hidden sm:rounded-lg border border-gray-200">
            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-lg font-bold text-gray-900">Playback Access History</h2>
                <p class="mt-1 text-xs text-gray-500">Immutable audit log of user playback requests.</p>
            </div>
            <div class="border-t border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($recording->accessLogs as $log)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ $log->created_at->format('M d, Y h:i:s A') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium">{{ $log->user ? $log->user->name : 'Anonymous / System' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ $log->action }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 font-mono text-xs">{{ $log->ip_address }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">No playback access records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
