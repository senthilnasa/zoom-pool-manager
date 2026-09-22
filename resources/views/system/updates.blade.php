@extends('layouts.base')

@section('title', 'System Updates — ' . config('app.name', 'Zoom Pool Manager'))
@section('page_title', 'System Updates & Releases')

@section('content')
<div class="space-y-6" x-data="systemUpdater()">

    <!-- Header & Action Ribbon -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-white flex items-center space-x-2">
                <span>Application Updates</span>
                <span class="px-2 py-0.5 text-xs font-semibold rounded-lg bg-sky-500/10 border border-sky-500/30 text-sky-400">
                    Official GitHub Channel
                </span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">
                Centrally manage, verify, and apply official updates released by <a href="{{ config('zpm.attribution.url') }}" target="_blank" class="text-sky-400 hover:underline">{{ config('zpm.attribution.author') }}</a>.
            </p>
        </div>

        <div class="flex items-center space-x-3">
            <button type="button"
                    @click="checkForUpdates()"
                    :disabled="checking"
                    class="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-200 border border-white/10 text-xs font-medium transition shadow-sm disabled:opacity-50">
                <svg class="w-4 h-4" :class="{ 'animate-spin': checking }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span x-text="checking ? 'Checking GitHub...' : 'Check for Updates'"></span>
            </button>

            @if($releaseInfo['update_available'])
                <button type="button"
                        @click="openConfirmModal()"
                        :disabled="updating || isLocked"
                        class="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white text-xs font-semibold shadow-glow transition disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Update Now</span>
                </button>
            @endif
        </div>
    </div>

    @if($isLocked)
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-center space-x-3">
            <svg class="w-5 h-5 shrink-0 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>An application update is currently in progress. Concurrent update actions are temporarily locked for safety.</span>
        </div>
    @endif

    <!-- Version Status Glass Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Card: Installed Version -->
        <div class="p-5 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/5 shadow-glass">
            <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Installed Version</span>
            <div class="mt-3 flex items-baseline space-x-2">
                <span class="text-2xl font-black text-white tracking-tight">v{{ $currentVersion }}</span>
                <span class="text-xs text-slate-400">Current</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">Running production release build</p>
        </div>

        <!-- Card: Latest Official Version -->
        <div class="p-5 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/5 shadow-glass">
            <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Latest Official Version</span>
            <div class="mt-3 flex items-baseline space-x-2">
                <span class="text-2xl font-black text-white tracking-tight" x-text="'v' + release.latest_version">v{{ $releaseInfo['latest_version'] }}</span>
                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md"
                      :class="release.update_available ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'"
                      x-text="release.update_available ? 'Update Available' : 'Up to Date'">
                    {{ $releaseInfo['update_available'] ? 'Update Available' : 'Up to Date' }}
                </span>
            </div>
            <p class="text-[11px] text-slate-400 mt-2" x-text="release.checked_at ? 'Last checked: ' + new Date(release.checked_at).toLocaleTimeString() : 'Checked just now'"></p>
        </div>

        <!-- Card: Source Repository -->
        <div class="p-5 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/5 shadow-glass">
            <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Project Attribution</span>
            <div class="mt-3 flex items-center space-x-2">
                <div class="w-7 h-7 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center font-bold text-xs border border-sky-500/30">
                    SN
                </div>
                <div>
                    <a href="{{ config('zpm.attribution.url') }}" target="_blank" class="text-xs font-semibold text-white hover:text-sky-400 transition">{{ config('zpm.attribution.author') }}</a>
                    <p class="text-[10px] text-slate-400">{{ config('zpm.repository') }}</p>
                </div>
            </div>
            <div class="mt-3">
                <a href="{{ config('zpm.repository_url') }}" target="_blank" class="text-[11px] text-sky-400 hover:underline flex items-center space-x-1">
                    <span>View Repository on GitHub</span>
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>
        </div>

    </div>

    <!-- Release Notes & Changelog Panel -->
    <div class="p-6 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/5 shadow-glass">
        <div class="flex items-center justify-between border-b border-white/5 pb-4 mb-4">
            <div>
                <h3 class="text-sm font-semibold text-white" x-text="release.release_name || 'Release Information'">{{ $releaseInfo['release_name'] ?? 'Release Information' }}</h3>
                <p class="text-xs text-slate-400 mt-0.5" x-text="release.published_at ? 'Published on ' + new Date(release.published_at).toLocaleDateString() : 'Official GitHub Release'"></p>
            </div>
            @if($releaseInfo['html_url'])
                <a :href="release.html_url" target="_blank" class="text-xs text-slate-400 hover:text-white transition flex items-center space-x-1">
                    <span>GitHub Release Page</span>
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            @endif
        </div>

        <div class="prose prose-invert prose-xs max-w-none text-slate-300 leading-relaxed font-sans bg-slate-950/40 p-5 rounded-xl border border-white/5 whitespace-pre-line" x-text="release.release_notes || 'No release notes available.'">
            {{ $releaseInfo['release_notes'] ?? 'No release notes available.' }}
        </div>
    </div>

    <!-- Safety & Architecture Guardrails -->
    <div class="p-5 rounded-2xl bg-slate-900/40 border border-white/5">
        <h4 class="text-xs font-semibold text-white flex items-center space-x-2">
            <svg class="w-4 h-4 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span>Automated Production Safeguards</span>
        </h4>
        <ul class="mt-2.5 text-xs text-slate-400 space-y-1.5 list-disc list-inside">
            <li>Automatic pre-update database backup is verified and stored outside the web root before files are modified.</li>
            <li>Environment credentials (<code class="text-slate-300">.env</code>), uploads, keys, and storage data are strictly protected and never overwritten.</li>
            <li>Downloaded release archives are validated for ZIP integrity, checksums, and path traversal security.</li>
            <li>Database migrations (<code class="text-slate-300">migrate --force</code>) and cache rebuilds run automatically.</li>
        </ul>
    </div>

    <!-- Update Confirmation & Live Progress Modal -->
    <div x-show="showModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
        <div class="bg-slate-900 border border-white/10 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4" @click.outside="if(!updating) showModal = false">
            
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center font-bold">
                    <svg class="w-6 h-6" :class="{ 'animate-spin': updating }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white" x-text="updating ? 'Applying Application Update...' : 'Confirm System Update'">Confirm System Update</h3>
                    <p class="text-xs text-slate-400" x-text="'Target release: v' + release.latest_version"></p>
                </div>
            </div>

            <!-- Steps Log Display -->
            <div class="p-3 bg-slate-950 rounded-xl border border-white/5 max-h-48 overflow-y-auto space-y-1.5 font-mono text-[11px] text-slate-300">
                <template x-for="(step, idx) in steps" :key="idx">
                    <div class="flex items-center space-x-2">
                        <span class="text-sky-400">→</span>
                        <span x-text="step"></span>
                    </div>
                </template>
                <div x-show="steps.length === 0" class="text-slate-400 italic">
                    Ready to initiate update lifecycle.
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex justify-end space-x-3 pt-2">
                <button type="button"
                        x-show="!updating && !updateFinished"
                        @click="showModal = false"
                        class="px-4 py-2 rounded-xl text-xs text-slate-400 hover:text-white transition">
                    Cancel
                </button>
                <button type="button"
                        x-show="!updating && !updateFinished"
                        @click="startUpdate()"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white text-xs font-semibold shadow-glow transition">
                    Begin Safe Update
                </button>
                <button type="button"
                        x-show="updateFinished"
                        @click="window.location.reload()"
                        class="px-4 py-2 rounded-xl bg-sky-500 hover:bg-sky-400 text-white text-xs font-semibold transition">
                    Reload Application
                </button>
            </div>

        </div>
    </div>

</div>

@push('scripts')
<script>
function systemUpdater() {
    return {
        checking: false,
        updating: false,
        updateFinished: false,
        showModal: false,
        isLocked: {{ $isLocked ? 'true' : 'false' }},
        release: @json($releaseInfo),
        steps: [],

        async checkForUpdates() {
            this.checking = true;
            try {
                const res = await ZPM.api('{{ route('admin.system.updates.check') }}', { method: 'POST' });
                this.release = res;
                if (res.error) {
                    ZPM.toast('error', res.error);
                } else if (res.update_available) {
                    ZPM.toast('success', `Update available: v${res.latest_version}`);
                } else {
                    ZPM.toast('info', 'Your application is up to date.');
                }
            } catch (err) {
                ZPM.toast('error', 'Failed to connect to update service.');
            } finally {
                this.checking = false;
            }
        },

        openConfirmModal() {
            this.steps = ['Ready to initiate update.'];
            this.updateFinished = false;
            this.showModal = true;
        },

        async startUpdate() {
            this.updating = true;
            this.steps = ['Initiating update process...', 'Validating package source...'];
            try {
                const res = await ZPM.api('{{ route('admin.system.updates.apply') }}', { method: 'POST' });
                if (res.steps) {
                    this.steps = res.steps;
                }
                if (res.success) {
                    this.updateFinished = true;
                    ZPM.toast('success', res.message || 'Application updated successfully!');
                } else {
                    ZPM.toast('error', res.message || 'Update failed.');
                }
            } catch (err) {
                const msg = (err.data && err.data.message) ? err.data.message : 'Update process encountered an error.';
                this.steps.push('Failed: ' + msg);
                ZPM.toast('error', msg);
            } finally {
                this.updating = false;
            }
        }
    };
}
</script>
@endpush
@endsection
