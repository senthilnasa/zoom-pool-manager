@extends('layouts.base')

@section('title', 'Notifications')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Notifications</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                In-app alerts for your bookings, approvals, and reminders.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex items-center space-x-3">
            @if($unreadCount > 0)
                <form action="{{ route('notifications.mark-all-read') }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700">
                        Mark All as Read
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 rounded-lg text-emerald-700 dark:text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <!-- Notifications List -->
    <div class="bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden divide-y divide-slate-100 dark:divide-slate-700">
        @forelse($notifications as $notification)
            @php
                $isUnread = is_null($notification->read_at);
                $data = $notification->data;
            @endphp
            <div class="p-5 flex items-start justify-between transition-colors {{ $isUnread ? 'bg-sky-50/50 dark:bg-sky-950/20' : '' }}">
                <div class="flex items-start space-x-4">
                    <div class="mt-1 flex-shrink-0">
                        @if($isUnread)
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-sky-600 dark:bg-sky-400"></span>
                        @else
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                        @endif
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $data['title'] ?? 'Notification' }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            {{ $data['message'] ?? '' }}
                        </p>
                        <div class="mt-2 flex items-center space-x-4 text-xs text-slate-400">
                            <span>{{ $notification->created_at->diffForHumans() }}</span>
                            @if(!empty($data['meeting_id']))
                                <a href="{{ route('meetings.show', $data['meeting_id']) }}" class="text-sky-600 dark:text-sky-400 hover:underline">
                                    View Meeting &rarr;
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                @if($isUnread)
                    <form action="{{ route('notifications.read', $notification->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1">
                            Mark read
                        </button>
                    </form>
                @endif
            </div>
        @empty
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No notifications</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">You are all caught up!</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($notifications->hasPages())
        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection
