@extends('layouts.base')

@section('title', $meeting->title . ' — Zoom Pool Manager')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $meeting->title }}</h1>
                @php
                    $statusStyles = [
                        'draft' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                        'requested' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
                        'approved' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
                        'scheduled' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300',
                        'live' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 animate-pulse',
                        'ended' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                        'cancelled' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300',
                        'failed' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300',
                    ];
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusStyles[$meeting->status] ?? 'bg-slate-100 text-slate-700' }}">
                    {{ ucfirst($meeting->status) }}
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">ID: {{ $meeting->public_id }} &bull; Created by {{ $meeting->requester?->name ?? 'System' }}</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('meetings.index') }}" class="inline-flex items-center px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 transition">
                &larr; Back to List
            </a>

            @if(!in_array($meeting->status, ['draft', 'cancelled', 'rejected']))
                <a href="{{ route('meetings.ics', $meeting->public_id) }}" class="inline-flex items-center px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 transition">
                    <svg class="w-4 h-4 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Add to Calendar (.ics)
                </a>
            @endif

            @inject('hostControl', 'App\Domain\HostControl\Services\HostControlService')
            @php
                $currentUser = auth()->user();
                $canHost = $currentUser && $hostControl->canAccessHostControls($meeting, $currentUser);
                $isMeetingOwner = $currentUser && ($meeting->owner_user_id === $currentUser->id || $meeting->requester_user_id === $currentUser->id);
                $isWithinWindow = $hostControl->isWithinStartWindow($meeting);
            @endphp

            @if ($canHost && !in_array($meeting->status, ['cancelled', 'ended', 'completed']))
                @if ($isMeetingOwner && $isWithinWindow)
                    <a href="{{ route('meetings.start', $meeting->public_id) }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm shadow-sm transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Start Meeting (Host)
                    </a>
                @elseif (! $isMeetingOwner)
                    <button type="button" onclick="document.getElementById('emergency-start-modal').classList.remove('hidden')" class="inline-flex items-center px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-xl text-sm shadow-sm transition">
                        IT Emergency Start
                    </button>
                @endif

                <button type="button" onclick="openHostKeyModal()" class="inline-flex items-center px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 transition">
                    Host Key
                </button>
            @endif

            @if ($meeting->canTransitionTo('cancelled'))
                <button type="button" onclick="document.getElementById('cancel-modal').classList.remove('hidden')" class="inline-flex items-center px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-xl text-sm transition">
                    Cancel Meeting
                </button>
            @endif
        </div>
    </div>

    @if ($errors->has('host_control'))
        <div class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl text-sm text-rose-700 dark:text-rose-300">
            {{ $errors->first('host_control') }}
        </div>
    @endif

    @if (session('status'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Details (2 cols) -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 space-y-6 shadow-sm">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white mb-2">Meeting Details</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-wrap">{{ $meeting->description ?: 'No description provided.' }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-slate-100 dark:border-slate-700/60">
                    <div>
                        <div class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Start Time</div>
                        <div class="text-sm font-medium text-slate-900 dark:text-white mt-1">
                            {{ $meeting->starts_at->format('M j, Y — g:i A') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400 uppercase tracking-wider font-semibold">End Time</div>
                        <div class="text-sm font-medium text-slate-900 dark:text-white mt-1">
                            {{ $meeting->ends_at->format('M j, Y — g:i A') }}
                            <span class="text-xs text-slate-400">({{ $meeting->duration_minutes }} mins)</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Meeting Type</div>
                        <div class="text-sm font-medium text-slate-900 dark:text-white mt-1">
                            {{ ucfirst($meeting->meeting_type) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Expected Attendees</div>
                        <div class="text-sm font-medium text-slate-900 dark:text-white mt-1">
                            {{ $meeting->participant_count }} participants
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60">
                    <div class="text-xs text-slate-400 uppercase tracking-wider font-semibold mb-2">Applied Configuration</div>
                    <div class="flex flex-wrap gap-2">
                        @if ($meeting->template)
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                                Template: {{ $meeting->template->name }}
                            </span>
                        @endif
                        @if ($meeting->securityProfile)
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                Profile: {{ $meeting->securityProfile->name }}
                            </span>
                        @endif
                    </div>
                </div>

                @if ($meeting->join_url)
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60">
                        <div class="text-xs text-slate-400 uppercase tracking-wider font-semibold mb-2">Zoom Connection</div>
                        <div class="p-4 bg-slate-50 dark:bg-slate-900/50 rounded-xl space-y-2 border border-slate-200 dark:border-slate-700">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-500">Join URL:</span>
                                <a href="{{ $meeting->join_url }}" target="_blank" rel="noopener noreferrer" class="text-xs text-sky-600 dark:text-sky-400 font-mono underline break-all">
                                    {{ $meeting->join_url }}
                                </a>
                            </div>
                            @if ($meeting->passcode)
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold text-slate-500">Passcode:</span>
                                    <span class="text-xs font-mono font-bold text-slate-900 dark:text-white">{{ $meeting->passcode }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <!-- Status History Timeline -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white mb-4">Lifecycle Audit Trail</h2>
                <div class="space-y-4">
                    @forelse ($meeting->statusHistory as $history)
                        <div class="flex items-start space-x-3 text-sm">
                            <div class="w-2 h-2 rounded-full bg-sky-500 mt-2"></div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-slate-900 dark:text-white">
                                        {{ $history->from_status ? ucfirst($history->from_status) . ' &rarr; ' : '' }}{{ ucfirst($history->to_status) }}
                                    </span>
                                    <span class="text-xs text-slate-400">{{ $history->created_at->format('M j, Y g:i A') }}</span>
                                </div>
                                @if ($history->reason)
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 italic">{{ $history->reason }}</p>
                                @endif
                                <p class="text-xs text-slate-400 mt-0.5">By {{ $history->actor?->name ?? 'System' }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">No lifecycle events recorded.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Sidebar Info (1 col) -->
        <div class="space-y-6">
            <!-- Resource Card -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Assigned Resource</h3>
                @if ($meeting->zoomResource)
                    <div class="p-3 bg-sky-50 dark:bg-sky-950/40 rounded-xl border border-sky-100 dark:border-sky-900">
                        <div class="font-medium text-sky-900 dark:text-sky-200 text-sm">
                            {{ $meeting->zoomResource->name }}
                        </div>
                        <div class="text-xs text-sky-600 dark:text-sky-400 mt-0.5">
                            Capacity: {{ $meeting->zoomResource->capacity }} participants
                        </div>
                    </div>
                @else
                    <div class="p-3 bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-200 dark:border-slate-700 text-xs text-slate-500">
                        No permanent resource assigned. Automatically selected or held.
                    </div>
                @endif
            </div>

            <!-- Invitees Card -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Invitees ({{ $meeting->invitees->count() }})</h3>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    @forelse ($meeting->invitees as $invitee)
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-100 dark:border-slate-700/60 last:border-none">
                            <span class="text-slate-800 dark:text-slate-200 truncate">{{ $invitee->email }}</span>
                            <span class="text-slate-400 capitalize">{{ $invitee->role }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">No invitees registered.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div id="cancel-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200 dark:border-slate-700">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Cancel Meeting</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Are you sure you want to cancel this meeting? Any held Zoom resources will be immediately released back into the pool.
        </p>

        <form action="{{ route('meetings.cancel', $meeting->public_id) }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="reason" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cancellation Reason</label>
                <textarea id="reason" name="reason" rows="3" required placeholder="Provide reason for cancellation..." class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-white focus:ring-rose-500 focus:border-rose-500"></textarea>
            </div>
            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="document.getElementById('cancel-modal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition">
                    Keep Meeting
                </button>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-sm font-semibold transition">
                    Confirm Cancellation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- IT Emergency Start Modal -->
<div id="emergency-start-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200 dark:border-slate-700">
        <div class="flex items-center space-x-2 text-amber-600 dark:text-amber-400 font-bold">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span>IT Emergency Host Start</span>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            You are starting this meeting with administrator privileges. A mandatory override reason is required and will be audited.
        </p>

        <form action="{{ route('meetings.start', $meeting->public_id) }}" method="GET" class="space-y-4">
            <div>
                <label for="emergency_reason" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Emergency Override Reason</label>
                <textarea id="emergency_reason" name="emergency_reason" rows="3" required placeholder="e.g. Instructor reported audio issue; IT technician joining to configure audio settings..." class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-white focus:ring-amber-500 focus:border-amber-500"></textarea>
            </div>
            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="document.getElementById('emergency-start-modal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition">
                    Dismiss
                </button>
                <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-semibold transition">
                    Start as Host
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Host Key Reveal Modal -->
<div id="host-key-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200 dark:border-slate-700">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Meeting Host Key</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Claim the host role inside Zoom using this 6-digit numeric host key. It is automatically rotated upon meeting conclusion.
        </p>

        @if (! $isMeetingOwner)
            <div id="hk-admin-reason-box" class="space-y-2">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">IT Override Reason (Mandatory)</label>
                <input type="text" id="hk-override-reason" placeholder="Reason for accessing host key..." class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-white">
            </div>
        @endif

        <div id="hk-display-box" class="hidden p-4 bg-slate-50 dark:bg-slate-900/60 rounded-xl text-center border border-slate-200 dark:border-slate-700">
            <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold block mb-1">Active Host PIN</span>
            <span id="hk-value" class="text-3xl font-mono font-black text-sky-600 dark:text-sky-400 tracking-widest">------</span>
        </div>

        <div id="hk-error-box" class="hidden p-3 bg-rose-50 dark:bg-rose-950/40 rounded-xl text-xs text-rose-700 dark:text-rose-300"></div>

        <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-700/60">
            @if ($currentUser && ($currentUser->hasRole('super_admin') || $currentUser->hasRole('it_admin')))
                <form action="{{ route('meetings.host-key.rotate', $meeting->public_id) }}" method="POST">
                    @csrf
                    <button type="submit" class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 underline">
                        Force Rotate Key
                    </button>
                </form>
            @else
                <div></div>
            @endif

            <div class="flex items-center space-x-2">
                <button type="button" onclick="document.getElementById('host-key-modal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition">
                    Close
                </button>
                <button type="button" id="hk-fetch-btn" onclick="fetchHostKey()" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-xl text-sm transition">
                    Reveal Host Key
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openHostKeyModal() {
    document.getElementById('host-key-modal').classList.remove('hidden');
}

async function fetchHostKey() {
    const errorBox = document.getElementById('hk-error-box');
    const displayBox = document.getElementById('hk-display-box');
    const valueEl = document.getElementById('hk-value');
    const reasonEl = document.getElementById('hk-override-reason');

    errorBox.classList.add('hidden');

    const payload = {};
    if (reasonEl) {
        payload.emergency_reason = reasonEl.value;
    }

    try {
        const res = await fetch('{{ route('meetings.host-key', $meeting->public_id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (res.ok && data.success) {
            displayBox.classList.remove('hidden');
            valueEl.innerText = data.host_key;
            document.getElementById('hk-fetch-btn').classList.add('hidden');
            if (document.getElementById('hk-admin-reason-box')) {
                document.getElementById('hk-admin-reason-box').classList.add('hidden');
            }
        } else {
            errorBox.innerText = data.message || 'Unable to retrieve host key.';
            errorBox.classList.remove('hidden');
        }
    } catch (e) {
        errorBox.innerText = 'Network error fetching host key.';
        errorBox.classList.remove('hidden');
    }
}
</script>
@endsection
