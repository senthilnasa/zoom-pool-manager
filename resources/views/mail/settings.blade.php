@extends('layouts.base')

@section('title', 'Mail Provider Configuration')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="mailSettings()">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Mail Configuration</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Configure your outbound MailProvider (SMTP, Gmail API, Microsoft 365 Graph, or Log).
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-3">
            <a href="{{ route('admin.mail.deliveries') }}" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700">
                Outbox Deliveries
            </a>
            <a href="{{ route('admin.templates.index') }}" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700">
                Email Templates
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 rounded-lg text-emerald-700 dark:text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <!-- Settings Form -->
    <form action="{{ route('admin.mail.update') }}" method="POST" class="bg-white dark:bg-slate-800 shadow-sm border border-slate-200 dark:border-slate-700 rounded-xl p-6 space-y-6">
        @csrf

        <!-- Provider Selection -->
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Active Mail Provider</label>
            <select name="provider" x-model="provider" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
                <option value="smtp">SMTP (Default Mail Server)</option>
                <option value="gmail">Google Workspace (Gmail API)</option>
                <option value="graph">Microsoft 365 (Graph API sendMail)</option>
                <option value="log">Log to File (Testing / Demo Mode)</option>
            </select>
        </div>

        <!-- Sender Identities -->
        <div class="border-t border-slate-200 dark:border-slate-700 pt-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">From Email Address</label>
                <input type="email" name="from_address" value="{{ old('from_address', $fromAddress) }}" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">From Name</label>
                <input type="text" name="from_name" value="{{ old('from_name', $fromName) }}" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reply-To Address (Optional)</label>
                <input type="email" name="reply_to" value="{{ old('reply_to', $replyTo) }}" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
            </div>
        </div>

        <!-- SMTP Options -->
        <div x-show="provider === 'smtp'" class="border-t border-slate-200 dark:border-slate-700 pt-6 space-y-4">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white uppercase tracking-wider">SMTP Server Settings</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">SMTP Host</label>
                    <input type="text" name="smtp_host" value="{{ old('smtp_host', $smtpHost) }}" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Port</label>
                    <input type="number" name="smtp_port" value="{{ old('smtp_port', $smtpPort) }}" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Encryption</label>
                    <select name="smtp_encryption" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
                        <option value="tls" {{ $smtpEncryption === 'tls' ? 'selected' : '' }}>TLS (STARTTLS)</option>
                        <option value="ssl" {{ $smtpEncryption === 'ssl' ? 'selected' : '' }}>SSL</option>
                        <option value="none" {{ $smtpEncryption === 'none' ? 'selected' : '' }}>None</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Username</label>
                    <input type="text" name="smtp_username" value="{{ old('smtp_username', $smtpUsername) }}" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
                    <input type="password" name="smtp_password" placeholder="Leave empty to preserve existing" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
                </div>
            </div>
        </div>

        <!-- Gmail API Options -->
        <div x-show="provider === 'gmail'" class="border-t border-slate-200 dark:border-slate-700 pt-6 space-y-4">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white uppercase tracking-wider">Gmail API Settings</h3>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">OAuth Access Token / Service Account Secret</label>
                <input type="password" name="gmail_access_token" placeholder="Leave blank to preserve" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
            </div>
        </div>

        <!-- Microsoft Graph Options -->
        <div x-show="provider === 'graph'" class="border-t border-slate-200 dark:border-slate-700 pt-6 space-y-4">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white uppercase tracking-wider">Microsoft 365 Graph Settings</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Sender User ID / UPN</label>
                    <input type="text" name="graph_sender_user" placeholder="me or user@organization.com" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Access Token</label>
                    <input type="password" name="graph_access_token" placeholder="Leave blank to preserve" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
                </div>
            </div>
        </div>

        <!-- Buttons -->
        <div class="flex items-center justify-between border-t border-slate-200 dark:border-slate-700 pt-6">
            <div class="flex space-x-3">
                <button type="button" @click="testConnection()" :disabled="testing" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700">
                    <span x-show="!testing">Test Connection</span>
                    <span x-show="testing">Testing...</span>
                </button>

                <button type="button" @click="showTestSendModal = true" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700">
                    Send Test Email
                </button>
            </div>

            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 shadow-sm">
                Save Mail Settings
            </button>
        </div>
    </form>

    <!-- Diagnostics Output -->
    <div x-show="testResult" class="p-4 rounded-lg text-sm border" :class="testResultSuccess ? 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200' : 'bg-rose-50 dark:bg-rose-950/50 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200'">
        <p x-text="testResultMessage"></p>
    </div>

    <!-- Test Send Modal -->
    <div x-show="showTestSendModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Send Test Email</h3>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Recipient Email</label>
                <input type="email" x-model="testEmail" placeholder="admin@organization.edu" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:text-sm">
            </div>
            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" @click="showTestSendModal = false" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-700 dark:text-slate-200">Cancel</button>
                <button type="button" @click="sendTestEmail()" :disabled="sendingTest" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-sm font-medium">
                    <span x-show="!sendingTest">Send</span>
                    <span x-show="sendingTest">Sending...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function mailSettings() {
    return {
        provider: '{{ $currentProvider }}',
        testing: false,
        testResult: false,
        testResultSuccess: false,
        testResultMessage: '',
        showTestSendModal: false,
        testEmail: '{{ auth()->user()?->email }}',
        sendingTest: false,

        async testConnection() {
            this.testing = true;
            this.testResult = false;
            try {
                const res = await fetch('{{ route("admin.mail.test-connection") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json();
                this.testResultSuccess = data.success;
                this.testResultMessage = data.message;
                this.testResult = true;
            } catch (err) {
                this.testResultSuccess = false;
                this.testResultMessage = 'Connection test failed: ' + err.message;
                this.testResult = true;
            } finally {
                this.testing = false;
            }
        },

        async sendTestEmail() {
            if (!this.testEmail) return;
            this.sendingTest = true;
            try {
                const res = await fetch('{{ route("admin.mail.test-send") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ test_email: this.testEmail })
                });
                const data = await res.json();
                this.showTestSendModal = false;
                this.testResultSuccess = data.success;
                this.testResultMessage = data.message;
                this.testResult = true;
            } catch (err) {
                this.testResultSuccess = false;
                this.testResultMessage = 'Send failed: ' + err.message;
                this.testResult = true;
            } finally {
                this.sendingTest = false;
            }
        }
    }
}
</script>
@endsection
