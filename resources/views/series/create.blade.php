@extends('layouts.base')

@section('title', 'Schedule Recurring Series — Zoom Pool Manager')

@section('content')
<div class="max-w-3xl mx-auto space-y-6"
     x-data="{
        freq: 'WEEKLY',
        count: 10,
        days: ['MO', 'WE', 'FR'],
        rrule: '',
        startsAt: '{{ old('starts_at', now()->addDay()->setHour(9)->setMinute(0)->format('Y-m-d\TH:00')) }}',

        computeRrule() {
            if (this.freq === 'DAILY') {
                this.rrule = `FREQ=DAILY;COUNT=${this.count}`;
            } else if (this.freq === 'WEEKLY') {
                const dayStr = this.days.length ? this.days.join(',') : 'MO';
                this.rrule = `FREQ=WEEKLY;BYDAY=${dayStr};COUNT=${this.count}`;
            } else if (this.freq === 'MONTHLY') {
                this.rrule = `FREQ=MONTHLY;COUNT=${this.count}`;
            }
        },

        toggleDay(day) {
            if (this.days.includes(day)) {
                this.days = this.days.filter(d => d !== day);
            } else {
                this.days.push(day);
            }
            this.computeRrule();
        }
     }"
     x-init="computeRrule()">

    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Schedule Recurring Series</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Automatically allocate pool resources across a multi-session schedule with blackout skipping.</p>
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

    <form action="{{ route('series.store') }}" method="POST" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 space-y-6 shadow-sm">
        @csrf

        <input type="hidden" name="rrule" :value="rrule">

        <!-- Title & Description -->
        <div class="space-y-4">
            <div>
                <label for="title" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Series Title</label>
                <input type="text" id="title" name="title" required value="{{ old('title') }}" placeholder="e.g. CS201 Data Structures — Fall 2026" class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-white focus:ring-sky-500 focus:border-sky-500">
            </div>

            <div>
                <label for="description" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Description (Optional)</label>
                <textarea id="description" name="description" rows="2" placeholder="Course syllabus or recurring agenda outline..." class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-white focus:ring-sky-500 focus:border-sky-500">{{ old('description') }}</textarea>
            </div>
        </div>

        <!-- Recurrence Pattern Builder -->
        <div class="p-4 bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-200 dark:border-slate-700 space-y-4">
            <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Recurrence Schedule</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Frequency</label>
                    <select x-model="freq" @change="computeRrule()" class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-white">
                        <option value="WEEKLY">Weekly</option>
                        <option value="DAILY">Daily (Monday to Friday / All)</option>
                        <option value="MONTHLY">Monthly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Occurrences Count (Max 50)</label>
                    <input type="number" min="2" max="50" x-model.number="count" @input="computeRrule()" class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-white">
                </div>
            </div>

            <div x-show="freq === 'WEEKLY'" class="space-y-2">
                <span class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Repeat on Days</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (['MO' => 'Mon', 'TU' => 'Tue', 'WE' => 'Wed', 'TH' => 'Thu', 'FR' => 'Fri', 'SA' => 'Sat', 'SU' => 'Sun'] as $code => $dayLabel)
                        <button type="button" @click="toggleDay('{{ $code }}')"
                                :class="days.includes('{{ $code }}') ? 'bg-sky-600 text-white font-bold' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600'"
                                class="px-3 py-1.5 rounded-lg text-xs transition">
                            {{ $dayLabel }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="pt-2 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between text-xs">
                <span class="text-slate-500">Generated iCalendar RRULE:</span>
                <code x-text="rrule" class="font-mono text-sky-600 dark:text-sky-400 font-bold"></code>
            </div>
        </div>

        <!-- Schedule Times -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="starts_at" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">First Session Date & Time</label>
                <input type="datetime-local" id="starts_at" name="starts_at" required value="{{ old('starts_at', now()->addDay()->setHour(9)->setMinute(0)->format('Y-m-d\TH:i')) }}" class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-white">
            </div>

            <div>
                <label for="duration_minutes" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Session Duration</label>
                <select id="duration_minutes" name="duration_minutes" class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-white">
                    <option value="30">30 minutes</option>
                    <option value="45">45 minutes</option>
                    <option value="60" selected>60 minutes (1 hour)</option>
                    <option value="90">90 minutes (1.5 hours)</option>
                    <option value="120">120 minutes (2 hours)</option>
                </select>
            </div>

            <div>
                <label for="participant_count" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Expected Attendees</label>
                <input type="number" id="participant_count" name="participant_count" min="1" value="{{ old('participant_count', 25) }}" class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-white">
            </div>
        </div>

        <!-- Series Allocation Mode -->
        <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Series Allocation Mode</label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <label class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer hover:border-sky-500 flex flex-col justify-between text-xs space-y-1">
                    <div class="flex items-center space-x-2">
                        <input type="radio" name="series_mode" value="SINGLE_RESOURCE" checked class="text-sky-600">
                        <span class="font-bold text-slate-900 dark:text-white">Single Resource</span>
                    </div>
                    <span class="text-slate-500">1 fixed Zoom recurring meeting ID & join link across all sessions.</span>
                </label>

                <label class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer hover:border-sky-500 flex flex-col justify-between text-xs space-y-1">
                    <div class="flex items-center space-x-2">
                        <input type="radio" name="series_mode" value="SPLIT_WHEN_NEEDED" class="text-sky-600">
                        <span class="font-bold text-slate-900 dark:text-white">Split When Needed</span>
                    </div>
                    <span class="text-slate-500">Uses primary resource, substitutes pool alternatives on conflicts.</span>
                </label>

                <label class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer hover:border-sky-500 flex flex-col justify-between text-xs space-y-1">
                    <div class="flex items-center space-x-2">
                        <input type="radio" name="series_mode" value="PER_OCCURRENCE" class="text-sky-600">
                        <span class="font-bold text-slate-900 dark:text-white">Per Occurrence</span>
                    </div>
                    <span class="text-slate-500">Independently optimizes resource allocation for each date.</span>
                </label>
            </div>
        </div>

        <input type="hidden" name="meeting_type" value="class">

        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100 dark:border-slate-700/60">
            <a href="{{ route('series.index') }}" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition">
                Cancel
            </a>
            <button type="submit" class="px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-xl text-sm shadow-sm transition">
                Schedule Recurring Series
            </button>
        </div>
    </form>
</div>
@endsection
