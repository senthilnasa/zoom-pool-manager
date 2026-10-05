<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
          <span>System Updates</span>
          <span class="text-xs px-2.5 py-0.5 rounded-full font-mono bg-brand-100 text-brand-700 dark:bg-brand-900/60 dark:text-brand-300">
            v{{ updateInfo?.installed_version || authStore.appVersion }}
          </span>
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Automated one-click application updates, pre-update snapshots, and database schema migrations.
        </p>
      </div>

      <button
        @click="checkUpdates"
        :disabled="checking || isUpdating"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-semibold transition-all disabled:opacity-50"
      >
        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': checking }" />
        <span>{{ checking ? 'Checking...' : 'Check for Updates' }}</span>
      </button>
    </div>

    <!-- Version Status Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <GlassCard title="Application Version">
        <div class="flex items-center gap-4 mt-2">
          <div class="w-12 h-12 rounded-2xl bg-brand-500/10 text-brand-600 dark:text-brand-400 flex items-center justify-center font-bold text-xl">
            <Layers class="w-6 h-6" />
          </div>
          <div>
            <div class="text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2">
              <span>v{{ updateInfo?.installed_version || authStore.appVersion }}</span>
            </div>
            <div class="text-xs text-slate-500 mt-0.5 space-x-2">
              <span>Build: <strong class="font-mono text-slate-700 dark:text-slate-300">{{ updateInfo?.metadata?.build || 'stable' }}</strong></span>
              <span v-if="updateInfo?.metadata?.release_date">• Released: {{ formatDate(updateInfo.metadata.release_date) }}</span>
            </div>
          </div>
        </div>
      </GlassCard>

      <GlassCard title="Latest Version">
        <div class="flex items-center gap-4 mt-2">
          <div
            class="w-12 h-12 rounded-2xl flex items-center justify-center font-bold text-xl"
            :class="updateInfo?.update_available ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
          >
            <ArrowUpCircle v-if="updateInfo?.update_available" class="w-6 h-6 text-emerald-500 animate-pulse" />
            <CheckCircle2 v-else class="w-6 h-6 text-emerald-500" />
          </div>
          <div>
            <div class="text-2xl font-black text-slate-900 dark:text-white">
              v{{ updateInfo?.latest_version || updateInfo?.installed_version || authStore.appVersion }}
            </div>
            <div class="text-xs font-medium mt-0.5" :class="updateInfo?.update_available ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500'">
              {{ updateInfo?.update_available ? 'New update available' : 'You are running the latest version' }}
            </div>
          </div>
        </div>
      </GlassCard>
    </div>

    <!-- Lock Warning Banner if Stale / Running Lock Detected -->
    <div
      v-if="updateInfo?.is_locked"
      class="p-4 rounded-2xl border border-amber-300 dark:border-amber-700/80 bg-amber-50/80 dark:bg-amber-950/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs"
    >
      <div class="flex items-center gap-3">
        <AlertTriangle class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0" />
        <div class="text-xs text-amber-800 dark:text-amber-200">
          <span class="font-bold">Update Lock Active:</span>
          An update lock is active on the server. If an update was interrupted or got stuck, click Reset to unlock the system.
        </div>
      </div>
      <button
        type="button"
        @click="resetUpdateLock"
        :disabled="resettingLock"
        class="shrink-0 px-3.5 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-xs transition"
      >
        {{ resettingLock ? 'Resetting...' : 'Unlock & Reset State' }}
      </button>
    </div>

    <!-- Update Action Banner if Update Available -->
    <div
      v-if="updateInfo?.update_available"
      class="p-6 rounded-2xl border border-emerald-200 dark:border-emerald-800/80 bg-gradient-to-r from-emerald-50/70 to-teal-50/70 dark:from-emerald-950/30 dark:to-teal-950/30 backdrop-blur-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-sm"
    >
      <div class="space-y-1">
        <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
          <Sparkles class="w-3.5 h-3.5" />
          <span>New Release Ready</span>
        </div>
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
          {{ updateInfo.release_name || 'Release v' + updateInfo.latest_version }}
        </h3>
        <p class="text-xs text-slate-600 dark:text-slate-300 max-w-xl">
          Automated one-click deployment will create a full database snapshot, update code files (safely preserving your <code class="font-mono text-xs">.env</code> and uploads), and run database migrations.
        </p>
      </div>

      <button
        @click="confirmModal = true"
        :disabled="isUpdating"
        class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-sm font-bold shadow-lg shadow-emerald-600/25 transition-all shrink-0 flex items-center gap-2"
      >
        <Download class="w-4 h-4" />
        <span>Update Now</span>
      </button>
    </div>

    <!-- Release Notes / Changelog -->
    <GlassCard v-if="updateInfo?.release_notes" title="Release Notes & Changelog">
      <div
        class="text-xs sm:text-sm text-slate-800 dark:text-slate-200 bg-slate-50/80 dark:bg-slate-900/60 p-5 sm:p-6 rounded-2xl border border-slate-200/60 dark:border-slate-800 max-h-[30rem] overflow-y-auto leading-relaxed custom-scrollbar markdown-content"
        v-html="renderedReleaseNotes"
      />
    </GlassCard>

    <!-- Confirmation Modal -->
    <Modal
      :show="confirmModal"
      title="Confirm System Update"
      @close="confirmModal = false"
    >
      <div class="space-y-4 text-sm text-slate-600 dark:text-slate-300">
        <p>
          You are about to update Zoom Pool Manager from
          <span class="font-semibold text-slate-900 dark:text-white">v{{ updateInfo?.installed_version }}</span>
          to
          <span class="font-bold text-emerald-600 dark:text-emerald-400">v{{ updateInfo?.latest_version }}</span>.
        </p>

        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
          <div class="font-semibold text-slate-900 dark:text-white flex items-center gap-1.5">
            <ShieldCheck class="w-4 h-4 text-brand-600" />
            <span>Automated Production Safeguards</span>
          </div>
          <ul class="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-400 pl-1">
            <li>Full database backup stored in <code class="font-mono">storage/app/backups/</code></li>
            <li>Zero loss of your <code class="font-mono">.env</code> configurations and uploaded files</li>
            <li>Automatic rollback to previous state if installation fails</li>
            <li>Database migrations applied automatically with zero manual SQL</li>
          </ul>
        </div>
      </div>

      <template #footer>
        <button
          type="button"
          @click="confirmModal = false"
          class="px-4 py-2 rounded-xl text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 text-sm font-semibold"
        >
          Cancel
        </button>
        <button
          type="button"
          @click="startUpdate"
          class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition-all shadow-md shadow-emerald-600/20"
        >
          Proceed with Update
        </button>
      </template>
    </Modal>

    <!-- Akaunting-Style Update Progress Modal -->
    <Modal
      :show="progressModal"
      title="Updating Zoom Pool Manager"
      :closeable="updateDone"
      @close="updateDone ? closeProgressModal() : null"
    >
      <div class="space-y-6">
        <!-- Progress Bar -->
        <div class="space-y-2">
          <div class="flex items-center justify-between text-xs font-semibold">
            <span class="text-slate-700 dark:text-slate-300">
              {{ currentStepMessage || 'Preparing update...' }}
            </span>
            <span class="text-brand-600 dark:text-brand-400 font-mono">
              {{ progressPercent }}%
            </span>
          </div>
          <div class="w-full h-2.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
            <div
              class="h-full transition-all duration-500 rounded-full"
              :class="updateFailed ? 'bg-rose-500' : 'bg-gradient-to-r from-brand-500 to-emerald-500'"
              :style="{ width: progressPercent + '%' }"
            />
          </div>
        </div>

        <!-- 7-Step Checklist -->
        <div class="divide-y divide-slate-100 dark:divide-slate-800/80 border border-slate-200/80 dark:border-slate-800 rounded-xl overflow-hidden bg-slate-50/50 dark:bg-slate-900/40">
          <div
            v-for="(step, idx) in updateSteps"
            :key="step.id || idx"
            class="flex items-center justify-between p-3.5 text-xs transition-colors"
            :class="getStepRowClass(step.status)"
          >
            <div class="flex items-center gap-3">
              <!-- Status Icon -->
              <div class="w-5 h-5 flex items-center justify-center shrink-0">
                <CheckCircle2 v-if="step.status === 'completed'" class="w-5 h-5 text-emerald-500" />
                <Loader2 v-else-if="step.status === 'running'" class="w-5 h-5 text-brand-600 animate-spin" />
                <AlertCircle v-else-if="step.status === 'failed'" class="w-5 h-5 text-rose-500" />
                <Circle v-else class="w-4 h-4 text-slate-300 dark:text-slate-600" />
              </div>
              <span class="font-medium" :class="getStepTextClass(step.status)">
                {{ step.name }}
              </span>
            </div>

            <span
              v-if="step.message && step.status !== 'pending'"
              class="text-[11px] truncate max-w-[200px] text-slate-500"
            >
              {{ step.status === 'completed' ? 'Done' : step.message }}
            </span>
          </div>
        </div>

        <!-- Success Completion Alert -->
        <div
          v-if="updateDone && !updateFailed"
          class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 flex items-start gap-3"
        >
          <CheckCircle2 class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" />
          <div class="text-xs space-y-1 flex-1">
            <div class="font-bold text-sm">Update completed successfully!</div>
            <p>Application files, database schema, and caches are now up to date on version <strong>v{{ updateInfo?.latest_version || updateInfo?.installed_version }}</strong>.</p>
            <p class="text-slate-600 dark:text-slate-300">You can reload the application now to refresh all assets, or close this dialog to review the log.</p>
          </div>
        </div>

        <!-- Failure Alert -->
        <div
          v-if="updateFailed"
          class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 flex items-start gap-3"
        >
          <AlertTriangle class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" />
          <div class="text-xs space-y-1">
            <div class="font-bold text-sm">Update Failed</div>
            <p>{{ errorMessage || 'An unexpected error occurred during update.' }}</p>
            <p v-if="rollbackStatus" class="font-semibold text-amber-700 dark:text-amber-300">
              Rollback Status: {{ rollbackStatus }}
            </p>
          </div>
        </div>

        <!-- Detailed Execution Logs Expander -->
        <div>
          <button
            type="button"
            @click="showLogs = !showLogs"
            class="text-xs font-semibold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 inline-flex items-center gap-1"
          >
            <span>{{ showLogs ? 'Hide detailed execution log' : 'Show detailed execution log' }}</span>
            <ChevronDown class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': showLogs }" />
          </button>
          <div
            v-if="showLogs"
            class="mt-2 p-3 bg-slate-900 text-slate-200 rounded-xl text-[11px] font-mono max-h-48 overflow-y-auto space-y-1 border border-slate-800"
          >
            <div v-for="(log, idx) in executionLogs" :key="idx">
              {{ log }}
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <div v-if="updateDone && !updateFailed" class="flex items-center gap-2">
          <button
            type="button"
            @click="closeProgressModal"
            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-all"
          >
            Close & Review Logs
          </button>
          <button
            type="button"
            @click="reloadPage"
            class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-md shadow-emerald-600/20 flex items-center gap-1.5"
          >
            <RefreshCw class="w-3.5 h-3.5" />
            <span>Reload Application Now</span>
          </button>
        </div>
        <div v-else class="flex items-center justify-between w-full gap-2">
          <button
            type="button"
            @click="resetUpdateLock"
            :disabled="resettingLock"
            class="px-3.5 py-2 rounded-xl text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-semibold transition-all inline-flex items-center gap-1.5"
            title="Force reset update lock and clear progress state"
          >
            <AlertTriangle class="w-3.5 h-3.5" />
            <span>{{ resettingLock ? 'Resetting...' : 'Stuck? Force Reset Lock' }}</span>
          </button>
          <button
            type="button"
            @click="closeProgressModal"
            class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-all"
          >
            Close
          </button>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { marked } from 'marked';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import GlassCard from '@/components/GlassCard.vue';
import Modal from '@/components/Modal.vue';
import {
  RefreshCw,
  CheckCircle2,
  ArrowUpCircle,
  Download,
  ShieldCheck,
  Layers,
  Sparkles,
  Loader2,
  AlertCircle,
  AlertTriangle,
  Circle,
  ChevronDown
} from 'lucide-vue-next';

const authStore = useAuthStore();
const toast = useToastStore();

const checking = ref(false);
const isUpdating = ref(false);
const confirmModal = ref(false);
const progressModal = ref(false);
const showLogs = ref(false);

const updateDone = ref(false);
const updateFailed = ref(false);
const errorMessage = ref('');
const rollbackStatus = ref('');
let pollInterval = null;

const updateInfo = ref({
  installed_version: '1.0.0',
  latest_version: '1.0.0',
  update_available: false,
  release_name: '',
  release_notes: '',
  metadata: {
    build: 'stable',
    release_date: '',
  }
});

const renderedReleaseNotes = computed(() => {
  const notes = updateInfo.value?.release_notes;
  if (!notes) return '';
  try {
    return marked.parse(notes, { gfm: true, breaks: true });
  } catch (e) {
    console.error('Error parsing release notes markdown:', e);
    return notes;
  }
});

const defaultSteps = [
  { id: 'checking', name: 'Checking latest version...', status: 'pending', message: null },
  { id: 'downloading', name: 'Downloading update...', status: 'pending', message: null },
  { id: 'backing_up', name: 'Creating backup...', status: 'pending', message: null },
  { id: 'installing', name: 'Installing update...', status: 'pending', message: null },
  { id: 'migrating', name: 'Running migrations...', status: 'pending', message: null },
  { id: 'restarting', name: 'Restarting application...', status: 'pending', message: null },
  { id: 'verifying', name: 'Verifying installation...', status: 'pending', message: null },
];

const updateSteps = ref([...defaultSteps]);
const progressPercent = ref(0);
const currentStepMessage = ref('');
const executionLogs = ref([]);

const formatDate = (isoString) => {
  if (!isoString) return '';
  try {
    const d = new Date(isoString);
    return isNaN(d.getTime()) ? isoString : d.toLocaleDateString();
  } catch {
    return isoString;
  }
};

const getStepRowClass = (status) => {
  if (status === 'running') return 'bg-brand-50/50 dark:bg-brand-950/20';
  if (status === 'completed') return 'bg-emerald-50/30 dark:bg-emerald-950/10';
  if (status === 'failed') return 'bg-rose-50/50 dark:bg-rose-950/20';
  return '';
};

const getStepTextClass = (status) => {
  if (status === 'running') return 'text-brand-600 dark:text-brand-400 font-semibold';
  if (status === 'completed') return 'text-slate-800 dark:text-slate-200';
  if (status === 'failed') return 'text-rose-600 dark:text-rose-400 font-semibold';
  return 'text-slate-400 dark:text-slate-500';
};

const resettingLock = ref(false);

const loadCurrentStatus = async () => {
  try {
    const res = await axios.get('/spa/settings/updates');
    if (res.data) {
      updateInfo.value = res.data;

      // If an update process is already running on the server, auto-attach to it
      if (res.data.progress && res.data.progress.is_active) {
        progressModal.value = true;
        isUpdating.value = true;
        if (Array.isArray(res.data.progress.steps)) {
          updateSteps.value = res.data.progress.steps;
        }
        if (typeof res.data.progress.percent === 'number') {
          progressPercent.value = res.data.progress.percent;
        }
        if (res.data.progress.current_step_name) {
          currentStepMessage.value = res.data.progress.current_step_name;
        }
        if (Array.isArray(res.data.progress.logs)) {
          executionLogs.value = res.data.progress.logs;
        }
        startPollingProgress();
      }
    }
  } catch (e) {
    updateInfo.value = {
      installed_version: authStore.appVersion || '1.0.0',
      latest_version: authStore.appVersion || '1.0.0',
      update_available: false,
    };
  }
};

const resetUpdateLock = async () => {
  try {
    resettingLock.value = true;
    await axios.post('/spa/settings/updates/reset');
    toast.success('Update lock cleared. System state has been reset.');
    stopPollingProgress();
    isUpdating.value = false;
    progressModal.value = false;
    updateDone.value = false;
    updateFailed.value = false;
    await loadCurrentStatus();
  } catch (e) {
    toast.error('Unable to reset update lock.');
  } finally {
    resettingLock.value = false;
  }
};

const checkUpdates = async () => {
  try {
    checking.value = true;
    const res = await axios.post('/spa/settings/updates/check');
    if (res.data) {
      updateInfo.value = res.data;
      if (res.data.update_available) {
        toast.info(`A newer release (v${res.data.latest_version}) is available!`);
      } else {
        toast.success('Your application is up to date.');
      }
    }
  } catch (e) {
    toast.error('Unable to fetch updates from GitHub repository.');
  } finally {
    checking.value = false;
  }
};

const startUpdate = async () => {
  confirmModal.value = false;
  progressModal.value = true;
  isUpdating.value = true;
  updateDone.value = false;
  updateFailed.value = false;
  errorMessage.value = '';
  rollbackStatus.value = '';
  progressPercent.value = 5;
  updateSteps.value = JSON.parse(JSON.stringify(defaultSteps));
  executionLogs.value = ['[' + new Date().toLocaleTimeString() + '] Initializing update sequence...'];

  // Start polling progress immediately
  startPollingProgress();

  try {
    const res = await axios.post('/spa/settings/updates/apply');
    if (res.data && res.data.success) {
      updateDone.value = true;
      progressPercent.value = 100;
      currentStepMessage.value = 'Update completed successfully.';
      // Mark all steps completed
      updateSteps.value.forEach(s => s.status = 'completed');
      toast.success(res.data.message || 'Application updated successfully.');
      loadCurrentStatus();
    } else {
      throw new Error(res.data?.message || 'Update failed.');
    }
  } catch (e) {
    updateFailed.value = true;
    updateDone.value = true;
    errorMessage.value = e.response?.data?.message || e.message || 'An error occurred during update.';
    if (e.response?.data?.can_rollback) {
      rollbackStatus.value = 'Application safely restored to original state.';
    }
  } finally {
    isUpdating.value = false;
    stopPollingProgress();
  }
};

const startPollingProgress = () => {
  stopPollingProgress();
  pollInterval = setInterval(async () => {
    try {
      const res = await axios.get('/spa/settings/updates/progress');
      if (res.data) {
        if (res.data.steps && Array.isArray(res.data.steps)) {
          updateSteps.value = res.data.steps;
        }
        if (typeof res.data.percent === 'number') {
          progressPercent.value = res.data.percent;
        }
        if (res.data.current_step_name) {
          currentStepMessage.value = res.data.current_step_name;
        }
        if (res.data.logs && Array.isArray(res.data.logs)) {
          executionLogs.value = res.data.logs;
        }
        if (res.data.error) {
          updateFailed.value = true;
          errorMessage.value = res.data.error;
        }
      }
    } catch {
      // Ignore transient network errors during maintenance reload
    }
  }, 1000);
};

const stopPollingProgress = () => {
  if (pollInterval) {
    clearInterval(pollInterval);
    pollInterval = null;
  }
};

const reloadPage = () => {
  window.location.reload();
};

const closeProgressModal = () => {
  progressModal.value = false;
  loadCurrentStatus();
};

onMounted(() => {
  loadCurrentStatus();
});

onUnmounted(() => {
  stopPollingProgress();
});
</script>
