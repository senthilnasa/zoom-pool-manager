@extends('layouts.base')

@section('title', 'Usage Quotas — Zoom Pool Manager')

@section('content')
<div class="space-y-6" x-data="{ showModal: false, scopeType: 'department' }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Resource Pool Quotas</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage monthly meeting counts and hours consumption caps per user and department for {{ $currentPeriod }}.</p>
        </div>
        <div class="flex items-center space-x-3">
            <button @click="showModal = true" class="inline-flex items-center px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-xl text-sm shadow-sm transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Set Quota Limit
            </button>
            <a href="{{ route('workflows.index') }}" class="inline-flex items-center px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                Workflow Rules
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
                        <th class="px-6 py-3.5">Target Scope</th>
                        <th class="px-6 py-3.5">Meetings Used / Limit</th>
                        <th class="px-6 py-3.5">Hours Used / Limit</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($quotas as $item)
                        @php
                            $q = $item['model'];
                            $s = $item['stats'];
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-750 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-semibold text-slate-900 dark:text-white block">{{ $item['target_name'] }}</span>
                                <span class="text-xs text-slate-400 capitalize">{{ $q->scope_type }} Quota</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                    {{ $s['meetings_count'] }} / {{ $s['max_meetings'] ?? '∞' }} meetings
                                </div>
                                @if ($s['percentage_meetings'] !== null)
                                    <div class="w-32 bg-slate-100 dark:bg-slate-700 h-1.5 rounded-full mt-1.5 overflow-hidden">
                                        <div class="h-full rounded-full {{ $s['percentage_meetings'] > 90 ? 'bg-rose-500' : 'bg-sky-500' }}" style="width: {{ min(100, $s['percentage_meetings']) }}%"></div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                    {{ $s['hours_used'] }} / {{ $s['max_hours'] ?? '∞' }} hours
                                </div>
                                @if ($s['percentage_hours'] !== null)
                                    <div class="w-32 bg-slate-100 dark:bg-slate-700 h-1.5 rounded-full mt-1.5 overflow-hidden">
                                        <div class="h-full rounded-full {{ $s['percentage_hours'] > 90 ? 'bg-rose-500' : 'bg-sky-500' }}" style="width: {{ min(100, $s['percentage_hours']) }}%"></div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $q->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $q->is_active ? 'Enforcing' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                                <form method="POST" action="{{ route('quotas.destroy', $q->public_id) }}" class="inline" onsubmit="return confirm('Remove quota limit?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 dark:text-rose-400 hover:underline font-medium">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                <p class="font-medium">No custom quota limits configured.</p>
                                <p class="text-xs mt-1">Users and departments can book resources without monthly count restrictions.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for adding/updating Quota -->
    <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="showModal = false"></div>

            <div class="relative bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4 border border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Configure Usage Quota</h3>

                <form method="POST" action="{{ route('quotas.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Quota Scope</label>
                        <select name="scope_type" x-model="scopeType" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2.5 text-slate-900 dark:text-white">
                            <option value="department">Department</option>
                            <option value="user">Specific User</option>
                        </select>
                    </div>

                    <div x-show="scopeType === 'department'">
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Select Department</label>
                        <select name="scope_id" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2.5 text-slate-900 dark:text-white" :disabled="scopeType !== 'department'">
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="scopeType === 'user'">
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Select User</label>
                        <select name="scope_id" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2.5 text-slate-900 dark:text-white" :disabled="scopeType !== 'user'">
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Max Meetings / Mo</label>
                            <input type="number" name="max_meetings_per_month" min="1" placeholder="Unlimited" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2.5 text-slate-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Max Hours / Mo</label>
                            <input type="number" name="max_hours_per_month" min="1" placeholder="Unlimited" class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2.5 text-slate-900 dark:text-white">
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 pt-2">
                        <input type="checkbox" id="is_active" name="is_active" value="1" checked class="rounded border-slate-300 text-sky-600">
                        <label for="is_active" class="text-xs text-slate-700 dark:text-slate-300">Enforce limit on booking submission</label>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <button type="button" @click="showModal = false" class="px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-xl text-xs shadow-sm transition">Save Limit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
