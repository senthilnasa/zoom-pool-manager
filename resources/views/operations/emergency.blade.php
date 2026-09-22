@extends('layouts.base')

@section('title', 'Emergency IT Control Panel')

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-red-600 flex items-center">
                <svg class="h-6 w-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Emergency IT Override Panel
            </h1>
            <p class="text-sm text-gray-500 mt-1">Direct intervention tools for administrators. Every action requires a mandatory reason, records an override audit, and alerts the meeting owner.</p>
        </div>
        <a href="{{ route('admin.health') }}" class="text-sm font-medium text-blue-600 hover:text-blue-500">
            &larr; Back to Health Dashboard
        </a>
    </div>

    <!-- Active Meetings Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg border border-red-200 mb-8" x-data="{ selectedMeeting: null, actionType: 'reallocate' }">
        <div class="px-6 py-4 bg-red-50 border-b border-red-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-red-900">Active & Upcoming Sessions</h3>
            <span class="text-xs text-red-700 font-medium">Select a meeting to perform emergency intervention</span>
        </div>

        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meeting Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scheduled Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Resource</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Override</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($activeMeetings as $m)
                    <tr class="hover:bg-red-50/50">
                        <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">{{ $m->title }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">{{ $m->owner ? $m->owner->name : 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">{{ $m->starts_at->format('M d, H:i') }} - {{ $m->ends_at->format('H:i') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-700 font-mono">{{ $m->zoomResource ? $m->zoomResource->name : 'Unallocated' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-800">{{ ucfirst($m->status) }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs space-x-2">
                            <button type="button" @click="selectedMeeting = '{{ $m->public_id }}'; actionType = 'reallocate'" class="text-blue-600 hover:text-blue-900 font-medium">Reallocate</button>
                            <button type="button" @click="selectedMeeting = '{{ $m->public_id }}'; actionType = 'cancel'" class="text-red-600 hover:text-red-900 font-medium">Force Cancel</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Intervention Modal / Inline Form -->
        <div x-show="selectedMeeting" class="p-6 bg-red-50 border-t border-red-200">
            <h4 class="text-sm font-bold text-red-900 mb-3" x-text="actionType === 'reallocate' ? 'Execute Emergency Reallocation' : 'Execute Emergency Force Cancellation'"></h4>
            <form method="POST" action="{{ route('admin.emergency.override') }}">
                @csrf
                <input type="hidden" name="meeting_public_id" :value="selectedMeeting">
                <input type="hidden" name="action" :value="actionType">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <template x-if="actionType === 'reallocate'">
                        <div>
                            <label for="resource_id" class="block text-xs font-semibold text-gray-700 mb-1">Target Host Resource</label>
                            <select name="resource_id" id="resource_id" class="w-full text-sm border-gray-300 rounded-md shadow-sm" required>
                                <option value="">Select Resource</option>
                                @foreach($resources as $res)
                                    <option value="{{ $res->id }}">{{ $res->name }} (Capacity: {{ $res->participant_capacity }})</option>
                                @endforeach
                            </select>
                        </div>
                    </template>
                    <div :class="actionType === 'reallocate' ? '' : 'col-span-2'">
                        <label for="reason" class="block text-xs font-semibold text-gray-700 mb-1">Mandatory Override Reason</label>
                        <input type="text" name="reason" id="reason" placeholder="Explain the operational justification for this emergency override..." class="w-full text-sm border-gray-300 rounded-md shadow-sm" required minlength="5">
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded shadow-sm">
                        Confirm Emergency Override
                    </button>
                    <button type="button" @click="selectedMeeting = null" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-medium rounded">
                        Dismiss
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
