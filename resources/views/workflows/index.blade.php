@extends('layouts.base')

@section('title', 'Workflow Rules — Zoom Pool Manager')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Workflow Governance Rules</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Configure automated routing, approval requirements, and policy overrides in priority order.</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('workflows.create') }}" class="inline-flex items-center px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-xl text-sm shadow-sm transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Workflow Rule
            </a>
            <a href="{{ route('quotas.index') }}" class="inline-flex items-center px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                Quotas
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">Priority</th>
                        <th class="px-6 py-3.5">Rule Name</th>
                        <th class="px-6 py-3.5">Conditions Summary</th>
                        <th class="px-6 py-3.5">Actions Summary</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($rules as $rule)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-750 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200">
                                    {{ $rule->priority }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-900 dark:text-white">
                                {{ $rule->name }}
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600 dark:text-slate-300 max-w-xs truncate">
                                @php
                                    $condSummary = [];
                                    if (isset($rule->conditions['duration_min']) || isset($rule->conditions['duration_max'])) {
                                        $condSummary[] = 'Duration (' . ($rule->conditions['duration_min'] ?? 0) . '-' . ($rule->conditions['duration_max'] ?? '∞') . 'm)';
                                    }
                                    if (isset($rule->conditions['participant_count_min']) || isset($rule->conditions['participant_count_max'])) {
                                        $condSummary[] = 'Attendees (' . ($rule->conditions['participant_count_min'] ?? 0) . '-' . ($rule->conditions['participant_count_max'] ?? '∞') . ')';
                                    }
                                    if (!empty($rule->conditions['working_hours_only'])) {
                                        $condSummary[] = 'Working Hours';
                                    }
                                    if (isset($rule->conditions['role'])) {
                                        $condSummary[] = 'Role: ' . implode(', ', (array) $rule->conditions['role']);
                                    }
                                @endphp
                                {{ !empty($condSummary) ? implode(', ', $condSummary) : 'Always Matches' }}
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600 dark:text-slate-300">
                                @if (!empty($rule->actions['auto_approve']))
                                    <span class="text-emerald-600 font-semibold">Auto-Approve</span>
                                @elseif (!empty($rule->actions['require_approval']))
                                    <span class="text-amber-600 font-semibold">Require Approval</span>
                                @elseif (!empty($rule->actions['reject']))
                                    <span class="text-rose-600 font-semibold">Reject</span>
                                @else
                                    <span class="text-slate-400">Override Settings</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form method="POST" action="{{ route('workflows.toggle', $rule->public_id) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer {{ $rule->is_enabled ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-300 dark:border-slate-600' }}">
                                        {{ $rule->is_enabled ? 'Enabled' : 'Disabled' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs space-x-2">
                                <a href="{{ route('workflows.edit', $rule->public_id) }}" class="text-sky-600 dark:text-sky-400 hover:underline font-medium">Edit</a>
                                <form method="POST" action="{{ route('workflows.destroy', $rule->public_id) }}" class="inline" onsubmit="return confirm('Delete this rule?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 dark:text-rose-400 hover:underline font-medium">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                <p class="font-medium">No workflow rules configured yet.</p>
                                <p class="text-xs mt-1">Default template settings and standard scheduling policies will apply.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rules->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $rules->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
