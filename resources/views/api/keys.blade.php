@extends('layouts.base')

@section('title', 'API Keys Management')

@section('content')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">API Keys & Integrations</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Manage scoped Bearer API tokens for programmatic integrations with external applications.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            <a href="/docs/api" target="_blank" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700">
                OpenAPI Docs
            </a>
            <a href="{{ route('admin.outbound-webhooks.index') }}" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700">
                Outbound Webhooks
            </a>
        </div>
    </div>

    @if (session('plainTextToken'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border-2 border-emerald-500 rounded-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                        API Key Created Successfully
                    </h3>
                    <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-300">
                        Please copy this key now. It will <strong>never</strong> be displayed again.
                    </p>
                    <div class="mt-2 flex items-center gap-2">
                        <input type="text" readonly value="{{ session('plainTextToken') }}" class="w-full font-mono text-sm bg-white dark:bg-slate-900 border border-emerald-300 dark:border-emerald-700 rounded px-3 py-1.5 select-all" />
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Key Generation Form -->
        <div class="lg:col-span-1 bg-white dark:bg-slate-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Create New API Key</h2>
            <form action="{{ route('admin.api-keys.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Key Name</label>
                    <input type="text" name="name" required placeholder="e.g. Canvas LMS Integration" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm shadow-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Granted Scopes</label>
                    <div class="space-y-2 text-sm">
                        <label class="flex items-center">
                            <input type="checkbox" name="scopes[]" value="meetings:read" checked class="rounded border-slate-300 text-indigo-600 shadow-sm" />
                            <span class="ml-2 text-slate-700 dark:text-slate-300">meetings:read</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="scopes[]" value="meetings:write" checked class="rounded border-slate-300 text-indigo-600 shadow-sm" />
                            <span class="ml-2 text-slate-700 dark:text-slate-300">meetings:write</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="scopes[]" value="availability:read" checked class="rounded border-slate-300 text-indigo-600 shadow-sm" />
                            <span class="ml-2 text-slate-700 dark:text-slate-300">availability:read</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="scopes[]" value="pools:read" class="rounded border-slate-300 text-indigo-600 shadow-sm" />
                            <span class="ml-2 text-slate-700 dark:text-slate-300">pools:read</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="scopes[]" value="recordings:read" class="rounded border-slate-300 text-indigo-600 shadow-sm" />
                            <span class="ml-2 text-slate-700 dark:text-slate-300">recordings:read</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Rate Limit (req / min)</label>
                    <input type="number" name="rate_limit" value="60" min="10" max="1000" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm shadow-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Expires After (Days)</label>
                    <input type="number" name="expires_days" placeholder="Never expires" min="1" max="365" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm shadow-sm" />
                </div>

                <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none">
                    Generate Key
                </button>
            </form>
        </div>

        <!-- Keys Table -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-lg shadow overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Active & Revoked Keys</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Name & Prefix</th>
                            <th class="px-4 py-3 text-left font-medium">Scopes</th>
                            <th class="px-4 py-3 text-left font-medium">Last Used</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse ($keys as $key)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ $key->name }}</div>
                                    <div class="text-xs font-mono text-slate-500">{{ $key->key_prefix }}...</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($key->scopes ?? [] as $scope)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200">
                                                {{ $scope }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">
                                    {{ $key->last_used_at ? $key->last_used_at->diffForHumans() : 'Never' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($key->revoked_at)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-300">
                                            Revoked
                                        </span>
                                    @elseif ($key->expires_at && $key->expires_at->isPast())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">
                                            Expired
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300">
                                            Active
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if (! $key->revoked_at)
                                        <form action="{{ route('admin.api-keys.revoke', $key->public_id) }}" method="POST" onsubmit="return confirm('Revoke this API key? External clients using it will immediately be rejected.');">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline">
                                                Revoke
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-slate-500">
                                    No API keys created yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($keys->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-700">
                    {{ $keys->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
