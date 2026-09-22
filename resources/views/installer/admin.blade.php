@extends('layouts.base')

@section('title', 'Super Admin & MFA Enrollment — Installer')

@section('content')
<div class="max-w-2xl mx-auto bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden"
     x-data="{
        email: '{{ old('email', 'admin@example.edu') }}',
        mfaSecret: '{{ old('mfa_secret', '') }}',
        qrCode: '',
        recoveryCodes: {{ json_encode(old('recovery_codes', [])) }},
        loadingMfa: false,
        async loadMfa() {
            if (!this.email) return;
            this.loadingMfa = true;
            try {
                const res = await fetch('{{ route('installer.admin.mfa') }}?email=' + encodeURIComponent(this.email));
                const data = await res.json();
                this.mfaSecret = data.secret;
                this.qrCode = data.qr_code;
                this.recoveryCodes = data.recovery_codes;
            } catch (e) {
                alert('Could not generate MFA secret.');
            } finally {
                this.loadingMfa = false;
            }
        },
        init() {
            this.loadMfa();
        }
     }">

    <div class="px-6 py-6 border-b border-slate-100 dark:border-slate-700">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Step 3: Initial Super Administrator & Mandatory MFA</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Create the primary emergency break-glass account. Local administrator accounts require mandatory Two-Factor Authentication (TOTP).
        </p>
    </div>

    <form method="POST" action="{{ route('installer.admin.save') }}">
        @csrf
        <input type="hidden" name="mfa_secret" :value="mfaSecret">
        <template x-for="code in recoveryCodes" :key="code">
            <input type="hidden" name="recovery_codes[]" :value="code">
        </template>

        <div class="p-6 space-y-5">
            @if($errors->any())
            <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-800 dark:text-rose-300">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Admin Credentials -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name</label>
                    <input type="text" name="name" value="{{ old('name', 'Super Administrator') }}" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Address</label>
                    <input type="email" name="email" x-model="email" @change="loadMfa" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Password (min 10 characters)</label>
                    <input type="password" name="password" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Confirm Password</label>
                    <input type="password" name="password_confirmation" required
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>
            </div>

            <!-- Mandatory MFA Section -->
            <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                <div class="flex items-center space-x-2 mb-3">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-sky-100 text-sky-800 text-xs font-bold">2FA</span>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Enroll Authenticator App (Mandatory)</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                    <!-- QR Code -->
                    <div class="flex flex-col items-center p-4 bg-slate-50 dark:bg-slate-800/80 rounded-lg border border-slate-200 dark:border-slate-700">
                        <div x-show="qrCode" class="bg-white p-2 rounded shadow-sm">
                            <img :src="qrCode" alt="MFA QR Code" class="w-44 h-44">
                        </div>
                        <div x-show="!qrCode" class="w-44 h-44 flex items-center justify-center text-xs text-slate-400">
                            Generating QR...
                        </div>
                        <p class="mt-2 text-[11px] text-slate-500 text-center">
                            Scan with Google Authenticator, Microsoft Authenticator, or 1Password.
                        </p>
                        <p class="mt-1 font-mono text-[10px] text-slate-600 dark:text-slate-400 select-all" x-text="'Secret: ' + mfaSecret"></p>
                    </div>

                    <!-- TOTP Verification Input -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Verification Code</label>
                            <input type="text" name="totp_code" maxlength="6" placeholder="123456" required
                                   class="w-full text-center tracking-widest text-lg font-mono font-bold px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none">
                            <span class="text-[11px] text-slate-500 mt-1 block">Enter the 6-digit code currently shown in your authenticator app.</span>
                        </div>

                        <!-- Recovery Codes -->
                        <div>
                            <span class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Emergency Recovery Codes</span>
                            <div class="p-2 bg-slate-100 dark:bg-slate-900 rounded font-mono text-[10px] text-slate-700 dark:text-slate-300 grid grid-cols-2 gap-1 select-all">
                                <template x-for="code in recoveryCodes" :key="code">
                                    <span x-text="code"></span>
                                </template>
                            </div>
                            <span class="text-[10px] text-amber-600 dark:text-amber-400 mt-1 block">Save these single-use recovery codes in your password manager.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700 flex justify-end">
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold shadow-sm transition">
                Verify & Save Administrator &rarr;
            </button>
        </div>
    </form>
</div>
@endsection
