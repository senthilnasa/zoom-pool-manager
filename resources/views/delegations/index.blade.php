@extends('layouts.base')

@section('title', 'Approval Delegations — Zoom Pool Manager')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('approvals.index') }}" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline flex items-center mb-1">
                &larr; Back to Approvals
            </a>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Approval Delegations</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Temporarily delegate your meeting review and sign-off authority to a colleague during absence or leave.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

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
        <!-- New Delegation Form (1 col) -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Grant Delegation</h3>
            <p class="text-xs text-slate-500">The selected colleague will be able to review and sign-off on your behalf during this period.</p>

            <form method="POST" action="{{ route('delegations.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Delegate Colleague</label>
                    <select name="delegate_user_id" required class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2.5 text-slate-900 dark:text-white">
                        <option value="">Select a user...</option>
                        @foreach ($eligibleDelegates as $delegate)
                            <option value="{{ $delegate->id }}">{{ $delegate->name }} ({{ $delegate->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Starts At</label>
                    <input type="datetime-local" name="starts_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2.5 text-slate-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Ends At</label>
                    <input type="datetime-local" name="ends_at" value="{{ now()->addWeek()->format('Y-m-d\TH:i') }}" required class="w-full text-sm rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 p-2.5 text-slate-900 dark:text-white">
                </div>

                <button type="submit" class="w-full py-2.5 px-4 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-xl text-xs shadow-sm transition">
                    Grant Approval Authority
                </button>
            </form>
        </div>

        <!-- Delegations Lists (2 cols) -->
        <div class="md:col-span-2 space-y-6">
            <!-- Delegations Granted by Me -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-4">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Delegations Granted by You</h3>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                        <thead class="text-xs font-semibold text-slate-500 uppercase">
                            <tr>
                                <th class="pb-2">Delegate</th>
                                <th class="pb-2">Validity Window</th>
                                <th class="pb-2">Status</th>
                                <th class="pb-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-750">
                            @forelse ($myDelegations as $del)
                                <tr>
                                    <td class="py-3 font-medium text-slate-900 dark:text-white">{{ $del->delegate->name }}</td>
                                    <td class="py-3 text-xs text-slate-500">
                                        {{ $del->starts_at->format('M d, H:i') }} &rarr; {{ $del->ends_at->format('M d, H:i') }}
                                    </td>
                                    <td class="py-3">
                                        @if ($del->is_active && $del->ends_at->isFuture())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">Active</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">Expired</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-right">
                                        @if ($del->is_active && $del->ends_at->isFuture())
                                            <form method="POST" action="{{ route('delegations.destroy', $del->public_id) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-rose-600 hover:underline font-semibold">Revoke</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-xs text-slate-400">
                                        You have not granted approval delegation to anyone.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Delegations Received from Others -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm space-y-4">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Delegated Authority Received</h3>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                        <thead class="text-xs font-semibold text-slate-500 uppercase">
                            <tr>
                                <th class="pb-2">Colleague</th>
                                <th class="pb-2">Valid Until</th>
                                <th class="pb-2">Authority Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-750">
                            @forelse ($delegatedToMe as $del)
                                <tr>
                                    <td class="py-3 font-medium text-slate-900 dark:text-white">{{ $del->user->name }}</td>
                                    <td class="py-3 text-xs text-slate-500">{{ $del->ends_at->diffForHumans() }} ({{ $del->ends_at->format('M d, H:i') }})</td>
                                    <td class="py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300">Authorized</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-xs text-slate-400">
                                        No active delegations have been granted to you.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
