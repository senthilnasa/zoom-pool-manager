@extends('layouts.base')

@section('title', 'Database Setup — Installer')

@section('content')
<div class="max-w-2xl mx-auto bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden"
     x-data="{
        host: '{{ old('host', config('database.connections.mariadb.host', '127.0.0.1')) }}',
        port: '{{ old('port', config('database.connections.mariadb.port', '3306')) }}',
        database: '{{ old('database', config('database.connections.mariadb.database', 'zpm')) }}',
        username: '{{ old('username', config('database.connections.mariadb.username', 'zpm')) }}',
        password: '{{ old('password', '') }}',
        testing: false,
        testResult: null,
        async testConnection() {
            this.testing = true;
            this.testResult = null;
            try {
                const res = await fetch('{{ route('installer.database.test') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({
                        host: this.host,
                        port: this.port,
                        database: this.database,
                        username: this.username,
                        password: this.password
                    })
                });
                this.testResult = await res.json();
            } catch (err) {
                this.testResult = { success: false, message: 'Network or unexpected error while testing connection.' };
            } finally {
                this.testing = false;
            }
        }
     }">

    <div class="px-6 py-6 border-b border-slate-100 dark:border-slate-700">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Step 2: Database Configuration</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure MySQL 8.0+ or MariaDB 10.6+ credentials and run initial database migrations.</p>
    </div>

    <form method="POST" action="{{ route('installer.database.save') }}">
        @csrf
        <div class="p-6 space-y-4">
            @if($errors->has('database'))
            <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-800 dark:text-rose-300">
                {{ $errors->first('database') }}
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Database Host</label>
                    <input type="text" name="host" x-model="host" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Port</label>
                    <input type="number" name="port" x-model="port" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Database Name</label>
                <input type="text" name="database" x-model="database" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username</label>
                    <input type="text" name="username" x-model="username" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Password</label>
                    <input type="password" name="password" x-model="password"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>
            </div>

            <!-- Test Connection Button -->
            <div class="pt-2">
                <button type="button" @click="testConnection" :disabled="testing"
                        class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                    <span x-show="!testing">Test Connection</span>
                    <span x-show="testing" x-cloak>Testing...</span>
                </button>
            </div>

            <!-- Test Connection Feedback -->
            <div x-show="testResult !== null" x-cloak class="mt-2 text-xs">
                <div x-show="testResult?.success" class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300">
                    <strong x-text="testResult?.type + ' detected: '"></strong>
                    <span x-text="testResult?.message"></span>
                </div>
                <div x-show="!testResult?.success" class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300">
                    <strong>Connection Failed:</strong> <span x-text="testResult?.message"></span>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700 flex justify-between items-center">
            <a href="{{ route('installer.requirements') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                &larr; Back
            </a>
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold shadow-sm transition">
                Migrate & Continue &rarr;
            </button>
        </div>
    </form>
</div>
@endsection
