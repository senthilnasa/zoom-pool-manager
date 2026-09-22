@extends('layouts.base')

@section('title', 'Organization Settings — Installer')

@section('content')
<div class="max-w-2xl mx-auto bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="px-6 py-6 border-b border-slate-100 dark:border-slate-700">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Step 4: Organization Profile & Timezone</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure your institution name and default scheduling constraints.</p>
    </div>

    <form method="POST" action="{{ route('installer.organization.save') }}">
        @csrf
        <div class="p-6 space-y-4">
            @if($errors->any())
            <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-800 dark:text-rose-300">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Organization / Institution Name</label>
                <input type="text" name="organization_name" value="{{ old('organization_name', 'Reference University') }}" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Default Timezone</label>
                    <select name="timezone" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
                        <option value="Asia/Kolkata" {{ old('timezone', 'Asia/Kolkata') === 'Asia/Kolkata' ? 'selected' : '' }}>Asia/Kolkata (IST +5:30)</option>
                        <option value="UTC" {{ old('timezone') === 'UTC' ? 'selected' : '' }}>UTC</option>
                        <option value="America/New_York" {{ old('timezone') === 'America/New_York' ? 'selected' : '' }}>America/New York (EST/EDT)</option>
                        <option value="America/Chicago" {{ old('timezone') === 'America/Chicago' ? 'selected' : '' }}>America/Chicago (CST/CDT)</option>
                        <option value="America/Los_Angeles" {{ old('timezone') === 'America/Los_Angeles' ? 'selected' : '' }}>America/Los Angeles (PST/PDT)</option>
                        <option value="Europe/London" {{ old('timezone') === 'Europe/London' ? 'selected' : '' }}>Europe/London (GMT/BST)</option>
                        <option value="Europe/Paris" {{ old('timezone') === 'Europe/Paris' ? 'selected' : '' }}>Europe/Paris (CET/CEST)</option>
                        <option value="Asia/Singapore" {{ old('timezone') === 'Asia/Singapore' ? 'selected' : '' }}>Asia/Singapore (SGT)</option>
                        <option value="Asia/Dubai" {{ old('timezone') === 'Asia/Dubai' ? 'selected' : '' }}>Asia/Dubai (GST)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Default Meeting Buffer (Minutes)</label>
                    <input type="number" name="buffer_minutes" min="10" max="60" value="{{ old('buffer_minutes', 10) }}" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
                    <span class="text-[11px] text-slate-500 mt-1 block">Cooldown buffer added to reservations (allowed: 10–60 min).</span>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700 flex justify-end">
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold shadow-sm transition">
                Finalize Installation &rarr;
            </button>
        </div>
    </form>
</div>
@endsection
