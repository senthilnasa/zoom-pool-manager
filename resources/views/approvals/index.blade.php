@extends('layouts.base')

@section('title', 'Pending Approvals — Zoom Pool Manager')

@section('content')
<div class="space-y-6" x-data="{ tab: 'pending' }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Workflow Approvals</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Review, approve, or reject meeting requests requiring governance sign-off.</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('delegations.index') }}" class="inline-flex items-center px-3.5 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <svg class="w-4 h-4 mr-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
                Manage Delegations
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if (! empty($delegatorIds))
        <div class="p-4 bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 rounded-xl text-xs text-sky-800 dark:text-sky-300 flex items-center">
            <svg class="w-4 h-4 mr-2 flex-shrink-0 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>You have active delegated authority to review approvals on behalf of other team members.</span>
        </div>
    @endif

    <!-- Tabs -->
    <div class="border-b border-slate-200 dark:border-slate-700">
        <nav class="-mb-px flex space-x-8">
            <button @click="tab = 'pending'" :class="tab === 'pending' ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition">
                Pending Reviews ({{ $pendingApprovals->total() }})
            </button>
            <button @click="tab = 'resolved'" :class="tab === 'resolved' ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition">
                Decided History
            </button>
        </nav>
    </div>

    <!-- Pending Reviews Table -->
    <div x-show="tab === 'pending'" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">Meeting Details</th>
                        <th class="px-6 py-3.5">Requester & Dept</th>
                        <th class="px-6 py-3.5">Scheduled Slot</th>
                        <th class="px-6 py-3.5">Review Deadline</th>
                        <th class="px-6 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($pendingApprovals as $approval)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-750 transition">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-900 dark:text-white">
                                    {{ $approval->meeting->title }}
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    Step {{ $approval->step }} &bull; {{ ucfirst($approval->meeting->meeting_type) }} ({{ $approval->meeting->participant_count }} attendees)
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                <div>{{ $approval->meeting->requester->name }}</div>
                                <div class="text-xs text-slate-400">{{ $approval->meeting->department?->name ?? 'Organization-wide' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                <div>{{ $approval->meeting->starts_at->format('M d, Y') }}</div>
                                <div class="text-xs text-slate-400">{{ $approval->meeting->starts_at->format('H:i') }} – {{ $approval->meeting->ends_at->format('H:i') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                @if ($approval->due_at)
                                    <span class="{{ $approval->due_at->isPast() ? 'text-rose-600 dark:text-rose-400 font-semibold' : '' }}">
                                        {{ $approval->due_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-slate-400">None</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <a href="{{ route('approvals.show', $approval->public_id) }}" class="inline-flex items-center px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg font-medium text-xs shadow-sm transition">
                                    Review Request
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                <p class="font-medium">No pending approvals waiting for your review.</p>
                                <p class="text-xs mt-1">All meeting booking requests are up to date.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($pendingApprovals->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $pendingApprovals->links() }}
            </div>
        @endif
    </div>

    <!-- Decided History Table -->
    <div x-show="tab === 'resolved'" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm" x-cloak>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">Meeting Details</th>
                        <th class="px-6 py-3.5">Decision</th>
                        <th class="px-6 py-3.5">Decided Date</th>
                        <th class="px-6 py-3.5">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($resolvedApprovals as $approval)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-750 transition">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-900 dark:text-white">
                                    {{ $approval->meeting->title }}
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ $approval->meeting->requester->name }} &bull; {{ $approval->meeting->starts_at->format('M d, Y H:i') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($approval->decision === 'approved')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        Approved
                                    </span>
                                @elseif ($approval->decision === 'rejected')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                        Rejected
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-50 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        {{ ucfirst($approval->decision) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-600 dark:text-slate-300 text-xs">
                                {{ $approval->decided_at ? $approval->decided_at->format('M d, Y H:i') : 'N/A' }}
                                @if ($approval->delegatedFrom)
                                    <div class="text-sky-600 dark:text-sky-400">On behalf of {{ $approval->delegatedFrom->name }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300 text-xs">
                                {{ $approval->decision_notes ?? 'None' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                <p class="font-medium">No past approval records.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($resolvedApprovals->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $resolvedApprovals->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
