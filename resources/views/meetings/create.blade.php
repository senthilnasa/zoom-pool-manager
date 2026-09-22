@extends('layouts.base')

@section('title', 'Book Meeting — Zoom Pool Manager')

@section('content')
<div class="max-w-3xl mx-auto space-y-6"
     x-data="{
        startsAt: '{{ old('starts_at', now()->addHours(3)->format('Y-m-d\TH:00')) }}',
        endsAt: '{{ old('ends_at', now()->addHours(4)->format('Y-m-d\TH:00')) }}',
        participantCount: {{ old('participant_count', 20) }},
        templateId: '{{ old('template_id', '') }}',
        profileId: '{{ old('security_profile_id', '') }}',
        poolId: '{{ old('preferred_pool_id', '') }}',
        checking: false,
        previewResult: null,

        async checkConflicts() {
            if (!this.startsAt || !this.endsAt) return;
            this.checking = true;
            try {
                const res = await fetch('{{ route('meetings.preview-conflicts') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        starts_at: this.startsAt,
                        ends_at: this.endsAt,
                        participant_count: this.participantCount,
                        template_id: this.templateId || null,
                        security_profile_id: this.profileId || null,
                        preferred_pool_id: this.poolId || null
                    })
                });
                this.previewResult = await res.json();
            } catch (e) {
                console.error(e);
            } finally {
                this.checking = false;
            }
        }
     }"
     x-init="checkConflicts()">

    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Request Pooled Zoom Meeting</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Book a session allocated automatically from the organization's licensed pool.</p>
    </div>

    @if ($errors->any())
        <div class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl text-sm text-rose-700 dark:text-rose-400">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Live Conflict Preview Notification Card -->
    <div x-show="previewResult" x-cloak class="p-4 rounded-xl border transition"
         :class="previewResult && previewResult.has_conflict ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-300 dark:border-amber-800 text-amber-900 dark:text-amber-200' : 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200'">
        <div class="flex items-start space-x-3">
            <template x-if="previewResult && previewResult.has_conflict">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </template>
            <template x-if="previewResult && !previewResult.has_conflict">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </template>
            <div class="flex-1">
                <div class="font-semibold text-sm" x-text="previewResult && previewResult.has_conflict ? 'Scheduling Conflict Detected' : 'Time Slot Free & Available (' + previewResult.available_resource_count + ' resources ready)'"></div>
                <template x-if="previewResult && previewResult.has_conflict">
                    <ul class="mt-1 text-xs list-disc list-inside space-y-0.5">
                        <template x-for="c in previewResult.conflicts" :key="c.type">
                            <li x-text="c.message"></li>
                        </template>
                    </ul>
                </template>
            </div>
            <div x-show="checking" class="text-xs opacity-75 animate-pulse">Checking availability...</div>
        </div>
    </div>

    <!-- Booking Form -->
    <form action="{{ route('meetings.store') }}" method="POST" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 sm:p-8 space-y-6 shadow-sm">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="sm:col-span-2">
                <label for="title" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Meeting Title</label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" required placeholder="e.g. Advanced Algorithms Lecture 10"
                       class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none">
            </div>

            <div>
                <label for="starts_at" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Start Time</label>
                <input type="datetime-local" name="starts_at" id="starts_at" x-model="startsAt" @change="checkConflicts()" required
                       class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none">
            </div>

            <div>
                <label for="ends_at" class="block text-sm font-medium text-slate-700 dark:text-slate-300">End Time</label>
                <input type="datetime-local" name="ends_at" id="ends_at" x-model="endsAt" @change="checkConflicts()" required
                       class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none">
            </div>

            <div>
                <label for="participant_count" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Expected Participants</label>
                <input type="number" name="participant_count" id="participant_count" x-model="participantCount" @change="checkConflicts()" min="1" max="1000" required
                       class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none">
            </div>

            <div>
                <label for="meeting_type" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Meeting Type</label>
                <select name="meeting_type" id="meeting_type"
                        class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none">
                    <option value="class">Faculty Class</option>
                    <option value="meeting">General Meeting</option>
                    <option value="exam">Examination / Proctored</option>
                    <option value="interview">Interview</option>
                    <option value="webinar">Webinar</option>
                </select>
            </div>

            <div>
                <label for="template_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Template (Optional)</label>
                <select name="template_id" id="template_id" x-model="templateId" @change="checkConflicts()"
                        class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none">
                    <option value="">Default Policy</option>
                    @foreach ($templates as $t)
                        <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->code }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="security_profile_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Security Profile (Optional)</label>
                <select name="security_profile_id" id="security_profile_id" x-model="profileId" @change="checkConflicts()"
                        class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none">
                    <option value="">Default Profile</option>
                    @foreach ($profiles as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <label for="invitees" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Invitee Emails (Optional)</label>
                <input type="text" name="invitees" id="invitees" value="{{ old('invitees') }}" placeholder="alice@university.edu, bob@university.edu"
                       class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none">
                <span class="text-xs text-slate-400 mt-1">Comma-separated internal or external participant emails.</span>
            </div>

            <div class="sm:col-span-2">
                <label for="description" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Description / Agenda (Optional)</label>
                <textarea name="description" id="description" rows="3"
                          class="mt-1 block w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none">{{ old('description') }}</textarea>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-6 border-t border-slate-100 dark:border-slate-700">
            <a href="{{ route('meetings.index') }}" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                Cancel
            </a>
            <button type="submit"
                    class="px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-xl text-sm shadow-sm transition">
                Submit Booking Request
            </button>
        </div>
    </form>
</div>
@endsection
