@extends('layouts.base')

@section('title', 'Outbox Email Deliveries')

@section('content')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Email Deliveries (Outbox)</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Audit log of all queued, sent, and failed transactional emails.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-3">
            <a href="{{ route('admin.mail.index') }}" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700">
                Mail Settings
            </a>
            <a href="{{ route('admin.templates.index') }}" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700">
                Templates
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex items-center space-x-2">
        <a href="{{ route('admin.mail.deliveries') }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ empty($status) ? 'bg-sky-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }}">All</a>
        <a href="{{ route('admin.mail.deliveries', ['status' => 'sent']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $status === 'sent' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }}">Sent</a>
        <a href="{{ route('admin.mail.deliveries', ['status' => 'queued']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $status === 'queued' ? 'bg-amber-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }}">Queued</a>
        <a href="{{ route('admin.mail.deliveries', ['status' => 'failed']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $status === 'failed' ? 'bg-rose-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }}">Failed</a>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 rounded-lg text-emerald-700 dark:text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/80">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Recipient</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Subject</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Template</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Attempts</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Sent At</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($deliveries as $deliv)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                            <span class="font-medium">{{ $deliv->recipient_name ?: $deliv->recipient_email }}</span>
                            <span class="block text-xs text-slate-400">{{ $deliv->recipient_email }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300 max-w-xs truncate">
                            {{ $deliv->subject }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-slate-500">
                            {{ $deliv->template_key }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($deliv->status === 'sent')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">Sent</span>
                            @elseif($deliv->status === 'queued')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">Queued</span>
                            @elseif($deliv->status === 'sending')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-300">Sending</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300">Failed</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500">
                            {{ $deliv->attempts }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400">
                            {{ $deliv->sent_at ? $deliv->sent_at->format('Y-m-d H:i') : '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium">
                            @if($deliv->status === 'failed')
                                <form action="{{ route('admin.mail.deliveries.retry', $deliv->public_id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-sky-600 hover:text-sky-900 dark:text-sky-400">Retry</button>
                                </form>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @if($deliv->error_message)
                        <tr class="bg-rose-50/50 dark:bg-rose-950/20">
                            <td colspan="7" class="px-6 py-2 text-xs text-rose-700 dark:text-rose-300 font-mono">
                                Error: {{ $deliv->error_message }}
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                            No emails logged in outbox yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($deliveries->hasPages())
        <div class="mt-4">
            {{ $deliveries->links() }}
        </div>
    @endif
</div>
@endsection
