@extends('layouts.base')

@section('title', 'Database Backups')

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Database Backups</h1>
            <p class="text-sm text-gray-500 mt-1">Automated and manual database exports stored securely outside the web root.</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.health') }}" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                &larr; Back to Health
            </a>
            <form method="POST" action="{{ route('admin.backups.create') }}">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    Create Backup Now
                </button>
            </form>
        </div>
    </div>

    <!-- Backups List -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
        @if($backups->count() > 0)
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Filename</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SHA-256 Checksum</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verification</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($backups as $backup)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ $backup->created_at->format('M d, Y H:i:s') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-xs font-medium text-gray-900">{{ $backup->filename }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ number_format($backup->file_size_bytes / 1024, 1) }} KB</td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-gray-400">{{ Str::limit($backup->checksum, 16) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $backup->verified_at ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $backup->verified_at ? 'Verified ✓' : 'Unverified' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                                <a href="{{ route('admin.backups.download', $backup->public_id) }}" class="text-blue-600 hover:text-blue-900 font-medium">Download</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No backup records yet</h3>
                <p class="mt-1 text-sm text-gray-500">Click "Create Backup Now" or let the scheduler run nightly backups.</p>
            </div>
        @endif
    </div>
</div>
@endsection
