@extends('layouts.base')

@section('title', 'Review Approval: ' . $meeting->title . ' — Zoom Pool Manager')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('approvals.index') }}" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline flex items-center mb-1">
                &larr; Back to Approvals
            </a>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Review Meeting Request</h1>
        </div>
        <div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                Pending Step {{ $approval->step }}
            </span>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl text-sm text-rose-700 dark:text-rose-300">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Left: Meeting & Conflict Info (2 cols) -->
        <div class="md:col-span-2 space-y-6">
            <!-- Details Card -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $meeting->title }}</h2>
                @if ($meeting->description)
                    <p class="text-sm text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/40 p-3 rounded-xl">
                        {{ $meeting->description }}
                    </p>
                @endif

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-2 text-sm">
                    <div>
                        <span class="text-xs text-slate-400 block uppercase font-semibold">Requester</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $meeting->requester->name }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block uppercase font-semibold">Department</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $meeting->department?->name ?? 'Organization-wide' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block uppercase font-semibold">Scheduled Time</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $meeting->starts_at->format('M d, Y') }}</span>
                        <span class="text-xs text-slate-400 block">{{ $meeting->starts_at->format('H:i') }} – {{ $meeting->ends_at->format('H:i') }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block uppercase font-semibold">Expected Attendees</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $meeting->participant_count }} participants</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block uppercase font-semibold">Security Profile</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $meeting->securityProfile?->name ?? 'Standard' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block uppercase font-semibold">Recording Mode</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ ucfirst($meeting->recording_mode) }}</span>
                    </div>
                </div>
            </div>

            <!-- Pool Availability / Conflict Check Preview -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">Resource Pool Availability Preview</h3>
                @if ($conflictResult->hasConflict)
                    <div class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl">
                        <div class="flex items-center text-amber-800 dark:text-amber-300 font-semibold text-sm mb-1">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            Potential Conflicts Detected
                        </div>
                        <ul class="text-xs text-amber-700 dark:text-amber-400 list-disc pl-5 space-y-0.5">
                            @foreach ($conflictResult->conflicts as $c)
                                <li>{{ $c['message'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-emerald-800 dark:text-emerald-300 text-sm flex items-center">
                        <svg class="w-5 h-5 mr-2 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Resource pool has available licensed capacity. Approving will automatically lock and confirm allocation.</span>
                    </div>
                @endif
            </div>

            <!-- Approval Chain Steps -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">Multi-Step Approval Chain</h3>
                <div class="space-y-3">
                    @foreach ($meeting->approvals->sortBy('step') as $app)
                        <div class="flex items-center justify-between p-3 rounded-xl border {{ $app->id === $approval->id ? 'border-sky-300 bg-sky-50/50 dark:border-sky-700 dark:bg-sky-950/20' : 'border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30' }}">
                            <div class="flex items-center space-x-3">
                                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold {{ $app->decision === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($app->decision === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-slate-200 text-slate-700') }}">
                                    {{ $app->step }}
                                </span>
                                <div>
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">
                                        Approver: {{ $app->approver->name }}
                                    </div>
                                    @if ($app->decision_notes)
                                        <div class="text-xs text-slate-500 mt-0.5">"{{ $app->decision_notes }}"</div>
                                    @endif
                                </div>
                            </div>
                            <div>
                                @if ($app->decision === 'approved')
                                    <span class="text-xs font-semibold text-emerald-600">Approved</span>
                                @elseif ($app->decision === 'rejected')
                                    <span class="text-xs font-semibold text-rose-600">Rejected</span>
                                @elseif ($app->decision === 'bypassed')
                                    <span class="text-xs font-semibold text-slate-400">Bypassed</span>
                                @else
                                    <span class="text-xs font-semibold text-amber-600">Pending</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Right: Action Form (1 col) -->
        <div class="space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Sign-off Decision</h3>
                <p class="text-xs text-slate-500">
                    Your decision will be cryptographically audited. Requester cannot approve their own meeting (Anti-Self-Approval Rule).
                </p>

                <form method="POST" action="{{ route('approvals.decide', $approval->public_id) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="decision_notes" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Review Comments / Justification
                        </label>
                        <textarea id="decision_notes" name="decision_notes" rows="4" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-3 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500" placeholder="Optional notes for approval, or reason if rejecting..."></textarea>
                    </div>

                    <div class="space-y-2 pt-2">
                        <button type="submit" name="decision" value="approved" class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm shadow-sm transition flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Approve Meeting Request
                        </button>
                        <button type="submit" name="decision" value="rejected" class="w-full py-2.5 px-4 rounded-xl border border-rose-300 dark:border-rose-700 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-700 dark:text-rose-300 font-semibold text-sm transition flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Reject Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
