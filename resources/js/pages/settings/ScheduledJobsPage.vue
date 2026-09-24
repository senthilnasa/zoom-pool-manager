<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
          <Clock class="w-6 h-6 text-brand-500 shrink-0" />
          <span>Scheduled Jobs & Automation Cadence</span>
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Configure background execution intervals, toggle active cron tasks, inspect daemon heartbeats, and trigger on-demand syncs.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchJobs"
          class="p-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-600 dark:text-slate-300 rounded-xl transition shadow-sm"
          title="Refresh Job Status"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>
      </div>
    </div>

    <!-- Daemon & System Telemetry -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="p-5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-brand-50 dark:bg-brand-950/40 text-brand-600 dark:text-brand-400 flex items-center justify-center shrink-0">
          <CheckCircle2 class="w-6 h-6" />
        </div>
        <div>
          <div class="text-2xl font-bold text-slate-900 dark:text-white">
            {{ daemonStatus.active_jobs_count || 0 }} / {{ daemonStatus.total_jobs_count || 0 }}
          </div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Active Automated Jobs</div>
        </div>
      </div>

      <div class="p-5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
        <div
          class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
          :class="daemonStatus.is_scheduler_healthy ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400' : 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400'"
        >
          <Activity class="w-6 h-6" />
        </div>
        <div>
          <div class="text-sm font-bold flex items-center gap-1.5" :class="daemonStatus.is_scheduler_healthy ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
            <span class="w-2 h-2 rounded-full" :class="daemonStatus.is_scheduler_healthy ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
            {{ daemonStatus.is_scheduler_healthy ? 'Daemon Operational' : 'Heartbeat Pending' }}
          </div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">
            Last beat: {{ daemonStatus.heartbeat_human || 'Pending' }}
          </div>
        </div>
      </div>

      <div class="p-5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
          <Cpu class="w-6 h-6" />
        </div>
        <div>
          <div class="text-sm font-bold text-slate-900 dark:text-white">Docker Background Worker</div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Laravel Schedule Daemon</div>
        </div>
      </div>

      <div class="p-5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
          <Server class="w-6 h-6" />
        </div>
        <div>
          <div class="text-xs font-bold text-slate-900 dark:text-white truncate">
            {{ formatServerTime(daemonStatus.server_time) }}
          </div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Platform Server Time</div>
        </div>
      </div>
    </div>

    <!-- Alert / Flash Banner -->
    <div
      v-if="flashMessage"
      class="p-4 rounded-xl text-sm flex items-center justify-between transition-all"
      :class="flashType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800' : 'bg-red-50 text-red-800 border border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-800'"
    >
      <div class="flex items-center gap-2">
        <CheckCircle2 v-if="flashType === 'success'" class="w-4 h-4 shrink-0" />
        <AlertCircle v-else class="w-4 h-4 shrink-0" />
        <span>{{ flashMessage }}</span>
      </div>
      <button @click="flashMessage = null" class="text-xs opacity-75 hover:opacity-100">&times;</button>
    </div>

    <!-- Category Filters & Search -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="flex items-center gap-2 overflow-x-auto pb-1 flex-1">
        <button
          v-for="cat in categories"
          :key="cat"
          @click="selectedCategory = cat"
          class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors"
          :class="selectedCategory === cat ? 'bg-brand-600 text-white shadow-sm' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/60'"
        >
          {{ cat }}
        </button>
      </div>

      <div class="relative w-full sm:w-64">
        <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search jobs or commands..."
          class="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Scheduled Jobs Table -->
    <div class="bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm overflow-hidden">
      <div v-if="!filteredJobs.length" class="p-12 text-center text-slate-400">
        <Clock class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No Matching Scheduled Jobs</p>
        <p class="text-xs text-slate-400 mt-1">Try selecting another category or adjusting your search query.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50/75 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-700/80 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              <th class="py-3.5 px-4">Job & Artisan Command</th>
              <th class="py-3.5 px-4">Category</th>
              <th class="py-3.5 px-4">Cadence (Interval)</th>
              <th class="py-3.5 px-4 text-center">Status</th>
              <th class="py-3.5 px-4">Last Run</th>
              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-xs">
            <tr
              v-for="job in filteredJobs"
              :key="job.key"
              class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition-colors"
            >
              <!-- Name & Command -->
              <td class="py-3.5 px-4">
                <div class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                  <span>{{ job.name }}</span>
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-1">
                  {{ job.description }}
                </div>
                <div class="mt-1.5 flex items-center gap-1.5">
                  <span class="inline-flex items-center px-2 py-0.5 rounded font-mono text-[10px] bg-slate-100 dark:bg-slate-700/80 text-slate-700 dark:text-slate-300">
                    {{ job.command }}
                  </span>
                </div>
              </td>

              <!-- Category -->
              <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                  {{ job.category }}
                </span>
              </td>

              <!-- Cadence Dropdown -->
              <td class="py-3.5 px-4 whitespace-nowrap">
                <select
                  :value="job.cadence"
                  @change="updateCadence(job.key, $event.target.value)"
                  class="px-2.5 py-1.5 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none text-xs"
                >
                  <option v-for="opt in cadenceOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </td>

              <!-- Enabled Toggle -->
              <td class="py-3.5 px-4 text-center whitespace-nowrap">
                <button
                  type="button"
                  @click="toggleEnabled(job.key, !job.enabled)"
                  class="relative inline-flex h-5 w-10 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                  :class="job.enabled ? 'bg-brand-600' : 'bg-slate-300 dark:bg-slate-700'"
                  :title="job.enabled ? 'Enabled (Click to disable)' : 'Disabled (Click to enable)'"
                >
                  <span
                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    :class="job.enabled ? 'translate-x-5' : 'translate-x-0'"
                  />
                </button>
              </td>

              <!-- Last Run -->
              <td class="py-3.5 px-4 whitespace-nowrap">
                <div v-if="job.last_run_at" class="space-y-1">
                  <div class="flex items-center gap-1.5">
                    <span
                      class="w-2 h-2 rounded-full"
                      :class="job.last_status === 'success' ? 'bg-emerald-500' : 'bg-rose-500'"
                    ></span>
                    <span class="font-medium text-slate-700 dark:text-slate-200">
                      {{ formatRelativeTime(job.last_run_at) }}
                    </span>
                  </div>
                  <div class="text-[10px] text-slate-400 dark:text-slate-500">
                    {{ job.last_run_at ? new Date(job.last_run_at).toLocaleTimeString() : '' }}
                  </div>
                </div>
                <div v-else class="text-slate-400 dark:text-slate-500 italic">
                  Not run yet
                </div>
              </td>

              <!-- Actions -->
              <td class="py-3.5 px-4 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-2">
                  <button
                    v-if="job.last_output"
                    @click="viewLog(job)"
                    class="px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700/60 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg transition"
                    title="View Last Execution Log"
                  >
                    Log
                  </button>

                  <button
                    @click="runJobNow(job.key)"
                    :disabled="runningJobKey === job.key"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-brand-600 hover:bg-brand-700 active:bg-brand-800 disabled:opacity-50 text-white text-[11px] font-semibold rounded-lg shadow-sm transition"
                  >
                    <Play v-if="runningJobKey !== job.key" class="w-3 h-3 fill-current" />
                    <RefreshCw v-else class="w-3 h-3 animate-spin" />
                    <span>{{ runningJobKey === job.key ? 'Running...' : 'Run Now' }}</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Execution Log Modal -->
    <div
      v-if="activeLogJob"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
    >
      <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl max-w-2xl w-full border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col max-h-[85vh]">
        <!-- Modal Header -->
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <Terminal class="w-5 h-5 text-brand-500" />
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-white">Execution Console Output</h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">{{ activeLogJob.command }}</p>
            </div>
          </div>
          <button
            @click="activeLogJob = null"
            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg"
          >
            &times;
          </button>
        </div>

        <!-- Terminal Output -->
        <div class="p-4 bg-slate-950 text-emerald-400 font-mono text-xs overflow-y-auto flex-1 space-y-2 select-text">
          <div class="text-slate-500 text-[11px]">
            # Executed: {{ activeLogJob.last_run_at ? new Date(activeLogJob.last_run_at).toLocaleString() : 'N/A' }} | Status: {{ activeLogJob.last_status }}
          </div>
          <pre class="whitespace-pre-wrap leading-relaxed">{{ activeLogJob.last_output }}</pre>
        </div>

        <!-- Modal Footer -->
        <div class="px-5 py-3 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <button
            @click="copyLog"
            class="px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition"
          >
            {{ copySuccess ? 'Copied!' : 'Copy Log' }}
          </button>
          <button
            @click="activeLogJob = null"
            class="px-4 py-1.5 text-xs font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Attribution Footer -->
    <div class="pt-6 border-t border-slate-200/80 dark:border-slate-700/80 text-center">
      <p class="text-xs text-slate-400 dark:text-slate-500">
        Zoom Pool Manager &bull; Enterprise Resource Allocation &bull; Made with ❤️ by
        <a
          href="https://github.com/senthilnasa"
          target="_blank"
          rel="noopener noreferrer"
          class="font-semibold text-brand-500 hover:underline inline-flex items-center gap-1"
        >
          Senthil Nasa
        </a>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import {
  Clock,
  RefreshCw,
  Play,
  CheckCircle2,
  AlertCircle,
  Activity,
  Cpu,
  Server,
  Terminal,
  Search,
} from 'lucide-vue-next';

const loading = ref(false);
const jobs = ref([]);
const cadenceOptions = ref([]);
const daemonStatus = ref({});
const selectedCategory = ref('All');
const runningJobKey = ref(null);
const activeLogJob = ref(null);
const copySuccess = ref(false);
const flashMessage = ref(null);
const flashType = ref('success');
const searchQuery = ref('');

const categories = computed(() => {
  const cats = new Set(jobs.value.map((j) => j.category).filter(Boolean));
  return ['All', ...Array.from(cats)];
});

const filteredJobs = computed(() => {
  let list = jobs.value;
  if (selectedCategory.value !== 'All') {
    list = list.filter((j) => j.category === selectedCategory.value);
  }
  if (!searchQuery.value.trim()) {
    return list;
  }
  const q = searchQuery.value.toLowerCase().trim();
  return list.filter((j) => {
    return (
      (j.name && j.name.toLowerCase().includes(q)) ||
      (j.description && j.description.toLowerCase().includes(q)) ||
      (j.command && j.command.toLowerCase().includes(q)) ||
      (j.key && j.key.toLowerCase().includes(q))
    );
  });
});

async function fetchJobs() {
  loading.value = true;
  try {
    const res = await axios.get('/spa/settings/jobs');
    jobs.value = res.data.jobs || [];
    cadenceOptions.value = res.data.cadence_options || [];
    daemonStatus.value = res.data.daemon_status || {};
  } catch (err) {
    console.error('Failed to load scheduled jobs', err);
    flashMessage.value = 'Failed to load background scheduled jobs.';
    flashType.value = 'error';
  } finally {
    loading.value = false;
  }
}

async function toggleEnabled(key, enabled) {
  try {
    const res = await axios.put(`/spa/settings/jobs/${key}`, { enabled });
    if (res.data.success) {
      const idx = jobs.value.findIndex((j) => j.key === key);
      if (idx !== -1) {
        jobs.value[idx].enabled = enabled;
      }
      // Update active count
      daemonStatus.value.active_jobs_count = jobs.value.filter((j) => j.enabled).length;
      flashMessage.value = `Updated automation state for ${key}.`;
      flashType.value = 'success';
      setTimeout(() => (flashMessage.value = null), 4000);
    }
  } catch (err) {
    console.error('Failed to toggle job', err);
    flashMessage.value = err.response?.data?.message || 'Failed to update job status.';
    flashType.value = 'error';
  }
}

async function updateCadence(key, cadence) {
  try {
    const res = await axios.put(`/spa/settings/jobs/${key}`, { cadence });
    if (res.data.success) {
      const idx = jobs.value.findIndex((j) => j.key === key);
      if (idx !== -1) {
        jobs.value[idx].cadence = cadence;
      }
      flashMessage.value = `Updated cadence frequency for ${key} to ${cadence}.`;
      flashType.value = 'success';
      setTimeout(() => (flashMessage.value = null), 4000);
    }
  } catch (err) {
    console.error('Failed to update cadence', err);
    flashMessage.value = err.response?.data?.message || 'Failed to update cadence.';
    flashType.value = 'error';
  }
}

async function runJobNow(key) {
  runningJobKey.value = key;
  try {
    const res = await axios.post(`/spa/settings/jobs/${key}/run`);
    const idx = jobs.value.findIndex((j) => j.key === key);
    if (idx !== -1) {
      jobs.value[idx].last_run_at = res.data.executed_at;
      jobs.value[idx].last_status = res.data.success ? 'success' : 'failed';
      jobs.value[idx].last_output = res.data.output;
      activeLogJob.value = jobs.value[idx];
    }
    flashMessage.value = res.data.message || `Execution finished for ${key}.`;
    flashType.value = res.data.success ? 'success' : 'error';
  } catch (err) {
    console.error('Failed to run job', err);
    flashMessage.value = err.response?.data?.message || 'Failed to trigger job execution.';
    flashType.value = 'error';
  } finally {
    runningJobKey.value = null;
  }
}

function viewLog(job) {
  activeLogJob.value = job;
}

function copyLog() {
  if (!activeLogJob.value?.last_output) return;
  navigator.clipboard.writeText(activeLogJob.value.last_output).then(() => {
    copySuccess.value = true;
    setTimeout(() => {
      copySuccess.value = false;
    }, 2000);
  });
}

function formatRelativeTime(isoString) {
  if (!isoString) return 'Never';
  const diff = Math.floor((Date.now() - new Date(isoString).getTime()) / 1000);
  if (diff < 60) return `${diff}s ago`;
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return `${Math.floor(diff / 86400)}d ago`;
}

function formatServerTime(isoString) {
  if (!isoString) return 'Connecting...';
  const d = new Date(isoString);
  return d.toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'medium',
  });
}

onMounted(() => {
  fetchJobs();
});
</script>
