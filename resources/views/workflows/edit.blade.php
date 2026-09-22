@extends('layouts.base')

@section('title', 'Edit Rule: ' . $rule->name . ' — Zoom Pool Manager')

@section('content')
<div class="space-y-6" x-data="{
    actionType: '{{ !empty($rule->actions['auto_approve']) ? 'auto_approve' : (!empty($rule->actions['reject']) ? 'reject' : (!empty($rule->actions['require_approval']) ? 'require_approval' : 'override')) }}',
    approverType: '{{ $rule->actions['require_approval']['approver_type'] ?? 'dept_admin' }}'
}">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('workflows.index') }}" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline flex items-center mb-1">
                &larr; Back to Workflow Rules
            </a>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Edit Workflow Rule</h1>
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

    <form method="POST" action="{{ route('workflows.update', $rule->public_id) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Rule Identity & Priority -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-4">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">1. Rule Definition</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Rule Name</label>
                    <input type="text" name="name" value="{{ old('name', $rule->name) }}" required class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2.5 text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Priority (1 = Highest)</label>
                    <input type="number" name="priority" value="{{ old('priority', $rule->priority) }}" min="1" max="1000" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2.5 text-slate-900 dark:text-white">
                </div>
            </div>
            <div class="flex items-center space-x-2 pt-2">
                <input type="hidden" name="is_enabled" value="0">
                <input type="checkbox" id="is_enabled" name="is_enabled" value="1" {{ $rule->is_enabled ? 'checked' : '' }} class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                <label for="is_enabled" class="text-xs font-medium text-slate-700 dark:text-slate-300">Rule is active</label>
            </div>
        </div>

        <!-- Conditions Section -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-4">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">2. Match Conditions</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Min Duration (Minutes)</label>
                    <input type="number" name="conditions[duration_min]" value="{{ $rule->conditions['duration_min'] ?? '' }}" placeholder="Any" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2 text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Max Duration (Minutes)</label>
                    <input type="number" name="conditions[duration_max]" value="{{ $rule->conditions['duration_max'] ?? '' }}" placeholder="Any" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2 text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Min Attendees</label>
                    <input type="number" name="conditions[participant_count_min]" value="{{ $rule->conditions['participant_count_min'] ?? '' }}" placeholder="Any" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2 text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Max Attendees</label>
                    <input type="number" name="conditions[participant_count_max]" value="{{ $rule->conditions['participant_count_max'] ?? '' }}" placeholder="Any" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2 text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Meeting Type</label>
                    <select name="conditions[meeting_type]" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2 text-slate-900 dark:text-white">
                        <option value="">Any Meeting Type</option>
                        <option value="meeting" {{ ($rule->conditions['meeting_type'] ?? '') === 'meeting' ? 'selected' : '' }}>General Meeting</option>
                        <option value="webinar" {{ ($rule->conditions['meeting_type'] ?? '') === 'webinar' ? 'selected' : '' }}>Webinar</option>
                        <option value="exam" {{ ($rule->conditions['meeting_type'] ?? '') === 'exam' ? 'selected' : '' }}>Proctored Exam</option>
                        <option value="interview" {{ ($rule->conditions['meeting_type'] ?? '') === 'interview' ? 'selected' : '' }}>Interview</option>
                        <option value="training" {{ ($rule->conditions['meeting_type'] ?? '') === 'training' ? 'selected' : '' }}>Training</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Department</label>
                    <select name="conditions[department_id]" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2 text-slate-900 dark:text-white">
                        <option value="">Any Department</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}" {{ (int)($rule->conditions['department_id'] ?? 0) === $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100 dark:border-slate-700">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="working_hours_only" name="conditions[working_hours_only]" value="1" {{ !empty($rule->conditions['working_hours_only']) ? 'checked' : '' }} class="rounded border-slate-300 text-sky-600">
                    <label for="working_hours_only" class="text-xs text-slate-700 dark:text-slate-300">Requires meeting to be during standard business hours</label>
                </div>
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="external_participants" name="conditions[external_participants]" value="1" {{ !empty($rule->conditions['external_participants']) ? 'checked' : '' }} class="rounded border-slate-300 text-sky-600">
                    <label for="external_participants" class="text-xs text-slate-700 dark:text-slate-300">Includes external attendees</label>
                </div>
            </div>
        </div>

        <!-- Actions Section -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-4">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">3. Governance Actions</h3>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <label class="border rounded-xl p-3 cursor-pointer transition flex items-center space-x-3" :class="actionType === 'require_approval' ? 'border-sky-500 bg-sky-50/50 dark:bg-sky-950/20' : 'border-slate-200 dark:border-slate-700'">
                    <input type="radio" name="action_selector" value="require_approval" x-model="actionType" class="text-sky-600">
                    <span class="text-xs font-semibold">Require Approval</span>
                </label>
                <label class="border rounded-xl p-3 cursor-pointer transition flex items-center space-x-3" :class="actionType === 'auto_approve' ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-700'">
                    <input type="radio" name="action_selector" value="auto_approve" x-model="actionType" class="text-emerald-600">
                    <span class="text-xs font-semibold">Auto-Approve</span>
                </label>
                <label class="border rounded-xl p-3 cursor-pointer transition flex items-center space-x-3" :class="actionType === 'reject' ? 'border-rose-500 bg-rose-50/50 dark:bg-rose-950/20' : 'border-slate-200 dark:border-slate-700'">
                    <input type="radio" name="action_selector" value="reject" x-model="actionType" class="text-rose-600">
                    <span class="text-xs font-semibold">Auto-Reject</span>
                </label>
                <label class="border rounded-xl p-3 cursor-pointer transition flex items-center space-x-3" :class="actionType === 'override' ? 'border-purple-500 bg-purple-50/50 dark:bg-purple-950/20' : 'border-slate-200 dark:border-slate-700'">
                    <input type="radio" name="action_selector" value="override" x-model="actionType" class="text-purple-600">
                    <span class="text-xs font-semibold">Overrides Only</span>
                </label>
            </div>

            <!-- Approval Config -->
            <div x-show="actionType === 'require_approval'" class="p-4 bg-slate-50 dark:bg-slate-900/40 rounded-xl space-y-4">
                <input type="hidden" name="actions[require_approval][enabled]" value="1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Approver Authority</label>
                        <select name="actions[require_approval][approver_type]" x-model="approverType" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2 text-slate-900 dark:text-white">
                            <option value="dept_admin" {{ ($rule->actions['require_approval']['approver_type'] ?? '') === 'dept_admin' ? 'selected' : '' }}>Department Administrator</option>
                            <option value="it_admin" {{ ($rule->actions['require_approval']['approver_type'] ?? '') === 'it_admin' ? 'selected' : '' }}>IT Administrator / Super Admin</option>
                            <option value="role" {{ ($rule->actions['require_approval']['approver_type'] ?? '') === 'role' ? 'selected' : '' }}>Specific Role</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Decision Quorum</label>
                        <select name="actions[require_approval][mode]" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2 text-slate-900 dark:text-white">
                            <option value="ANY" {{ ($rule->actions['require_approval']['mode'] ?? '') === 'ANY' ? 'selected' : '' }}>ANY (First approval advances)</option>
                            <option value="ALL" {{ ($rule->actions['require_approval']['mode'] ?? '') === 'ALL' ? 'selected' : '' }}>ALL (All assigned approvers must sign-off)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Auto-Approve Config -->
            <div x-show="actionType === 'auto_approve'">
                <input type="hidden" name="actions[auto_approve]" value="1">
            </div>

            <!-- Reject Config -->
            <div x-show="actionType === 'reject'" class="p-4 bg-rose-50 dark:bg-rose-950/40 rounded-xl space-y-2">
                <label class="block text-xs font-medium text-rose-800 dark:text-rose-300">Rejection Reason Message</label>
                <input type="text" name="actions[reject]" value="{{ is_string($rule->actions['reject'] ?? null) ? $rule->actions['reject'] : '' }}" placeholder="Reason for rejecting..." class="w-full text-sm rounded-xl border border-rose-300 dark:border-rose-700 bg-white dark:bg-slate-900 p-2 text-slate-900 dark:text-white">
            </div>

            <!-- Overrides Config -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-3 border-t border-slate-100 dark:border-slate-700">
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Assign Resource Pool</label>
                    <select name="actions[assign_pool]" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2 text-slate-900 dark:text-white">
                        <option value="">No Pool Override</option>
                        @foreach ($pools as $pool)
                            <option value="{{ $pool->id }}" {{ (int)($rule->actions['assign_pool'] ?? 0) === $pool->id ? 'selected' : '' }}>{{ $pool->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Assign Security Profile</label>
                    <select name="actions[assign_profile]" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2 text-slate-900 dark:text-white">
                        <option value="">No Profile Override</option>
                        @foreach ($profiles as $profile)
                            <option value="{{ $profile->id }}" {{ (int)($rule->actions['assign_profile'] ?? 0) === $profile->id ? 'selected' : '' }}>{{ $profile->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Recording Mode</label>
                    <select name="actions[enable_recording]" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2 text-slate-900 dark:text-white">
                        <option value="">No Recording Override</option>
                        <option value="cloud" {{ ($rule->actions['enable_recording'] ?? '') === 'cloud' ? 'selected' : '' }}>Force Cloud Recording</option>
                        <option value="local" {{ ($rule->actions['enable_recording'] ?? '') === 'local' ? 'selected' : '' }}>Force Local Recording</option>
                        <option value="none" {{ ($rule->actions['enable_recording'] ?? '') === 'none' ? 'selected' : '' }}>Disable Recording</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3">
            <a href="{{ route('workflows.index') }}" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">Cancel</a>
            <button type="submit" class="px-5 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-xl text-sm shadow-sm transition">Update Workflow Rule</button>
        </div>
    </form>
</div>
@endsection
