@extends('layouts.base')

@section('title', 'Cloud Recordings')

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Cloud Recordings</h1>
            <p class="text-sm text-gray-500 mt-1">Recordings of meetings you logically own or manage.</p>
        </div>
    </div>

    <!-- Recording Consent Notice (SPEC Part H11) -->
    <div class="mb-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-md">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    <strong>Recording Policy & Consent:</strong> Recordings stored in Zoom Cloud are accessible strictly to the authorized meeting owner and designated department administrators. Access to recordings is audited.
                </p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
        <form method="GET" action="{{ route('recordings.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="search" class="block text-xs font-medium text-gray-700 mb-1">Search Topic / Title</label>
                <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Search by topic..." class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" id="status" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Statuses</option>
                    <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="processing" {{ $status === 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    Filter Recordings
                </button>
            </div>
        </form>
    </div>

    <!-- Recordings List -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
        @if($recordings->count() > 0)
            <ul class="divide-y divide-gray-200">
                @foreach($recordings as $rec)
                    <li class="hover:bg-gray-50 transition">
                        <div class="px-4 py-4 sm:px-6 flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center space-x-3">
                                    <span class="p-2 rounded-full bg-red-100 text-red-600">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </span>
                                    <div>
                                        <h2 class="text-base font-semibold text-gray-900 truncate">
                                            <a href="{{ route('recordings.show', $rec->public_id) }}" class="hover:text-blue-600">
                                                {{ $rec->topic ?: 'Recorded Meeting' }}
                                            </a>
                                        </h2>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            Recorded on: {{ $rec->recording_start ? $rec->recording_start->format('M d, Y h:i A') : 'N/A' }} 
                                            &bull; Duration: {{ $rec->duration_minutes }} mins
                                            &bull; Size: {{ number_format($rec->file_size_bytes / 1048576, 1) }} MB
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $rec->status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ ucfirst($rec->status) }}
                                </span>
                                @if($rec->status === 'completed')
                                    <a href="{{ route('recordings.play', $rec->public_id) }}" target="_blank" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                                        Play
                                    </a>
                                @endif
                                <a href="{{ route('recordings.show', $rec->public_id) }}" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                    Details
                                </a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="p-4 border-t border-gray-200">
                {{ $recordings->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No cloud recordings found</h3>
                <p class="mt-1 text-sm text-gray-500">Recordings will automatically appear here once meetings finish recording.</p>
            </div>
        @endif
    </div>
</div>
@endsection
