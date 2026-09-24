<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Privacy, Compliance & Retention (GDPR / FERPA)
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Data Subject Access Requests (DSAR), user account anonymization, and scheduled retention purging
        </p>
      </div>

      <button
        @click="fetchStats"
        class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition self-start sm:self-auto"
        title="Refresh"
      >
        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
      </button>
    </div>

    <!-- Feedback Message -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="glass-card rounded-2xl p-5 border border-slate-200/60 dark:border-slate-800/60 space-y-1">
        <span class="text-xs text-slate-400 font-medium">Total Registered Users</span>
        <div class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.user_count || 0 }}</div>
      </div>
      <div class="glass-card rounded-2xl p-5 border border-slate-200/60 dark:border-slate-800/60 space-y-1">
        <span class="text-xs text-slate-400 font-medium">Active Data Exports</span>
        <div class="text-2xl font-black text-brand-600 dark:text-brand-400">{{ stats.active_exports || 0 }}</div>
      </div>
      <div class="glass-card rounded-2xl p-5 border border-slate-200/60 dark:border-slate-800/60 space-y-1">
        <span class="text-xs text-slate-400 font-medium">Lifetime DSAR Exports</span>
        <div class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.total_exports || 0 }}</div>
      </div>
      <div class="glass-card rounded-2xl p-5 border border-slate-200/60 dark:border-slate-800/60 space-y-1">
        <span class="text-xs text-slate-400 font-medium">Immutable Audit Records</span>
        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ stats.audit_log_count || 0 }}</div>
      </div>
    </div>

    <!-- Action Panels -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- 1. Data Export & Anonymization -->
      <div class="glass-card rounded-2xl p-6 border border-slate-200/60 dark:border-slate-800/60 space-y-4">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
          <UserX class="w-4 h-4 text-brand-500" />
          <span>Data Subject Requests (Right to Access / Erasure)</span>
        </h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          Generate an immediate JSON archive of all personal meetings and recordings, or permanently redact identity for departed personnel.
        </p>

        <div class="space-y-3 pt-2">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Target User ID *</label>
            <input
              v-model.number="targetUserId"
              type="number"
              placeholder="Enter User Database ID"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div class="flex items-center gap-2 pt-2">
            <button
              @click="exportUser"
              :disabled="exporting || !targetUserId"
              class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-brand-50 dark:bg-brand-950/40 text-brand-600 dark:text-brand-400 border border-brand-200 dark:border-brand-800 hover:bg-brand-100 transition shadow-sm disabled:opacity-50"
            >
              <Download class="w-3.5 h-3.5" />
              <span>{{ exporting ? 'Exporting...' : 'Generate User Export (JSON)' }}</span>
            </button>

            <button
              @click="openAnonymizeModal"
              :disabled="!targetUserId"
              class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900 hover:bg-rose-100 transition shadow-sm disabled:opacity-50"
            >
              <Trash2 class="w-3.5 h-3.5" />
              <span>Anonymize Identity</span>
            </button>
          </div>
        </div>
      </div>

      <!-- 2. Data Retention Purging -->
      <div class="glass-card rounded-2xl p-6 border border-slate-200/60 dark:border-slate-800/60 space-y-4">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
          <Clock class="w-4 h-4 text-amber-500" />
          <span>Automated Data Retention & Purge</span>
        </h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          Enforce compliance policies by permanently purging ephemeral webhook events, outbox delivery logs, and recording audit telemetry older than the retention threshold.
        </p>

        <div class="space-y-3 pt-2">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Retention Window (Days)</label>
            <select
              v-model="retentionDays"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option :value="30">30 Days (Strict Compliance)</option>
              <option :value="90">90 Days (Quarterly Standard)</option>
              <option :value="180">180 Days (Semi-Annual)</option>
              <option :value="365">365 Days (1 Year Academic Cycle)</option>
            </select>
          </div>

          <div class="pt-2">
            <button
              @click="purgeRetention"
              :disabled="purging"
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 shadow-md shadow-amber-500/20 transition disabled:opacity-50"
            >
              <AlertTriangle class="w-3.5 h-3.5" />
              <span>{{ purging ? 'Purging Old Records...' : 'Execute Retention Purge' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Exports Table -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div class="px-6 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Recent Data Subject Exports</h2>
        <span class="text-xs text-slate-400">Retention: 7 days</span>
      </div>

      <div v-if="!stats.recent_exports?.length" class="p-8 text-center text-slate-400 text-xs">
        No recent data exports requested.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Subject User</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Requested By</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Expires At</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
            <tr
              v-for="exp in stats.recent_exports"
              :key="exp.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
            >
              <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                {{ exp.user?.name || `User #${exp.user_id}` }}
              </td>
              <td class="py-3 px-4 text-slate-600 dark:text-slate-400">
                {{ exp.requested_by?.name || 'Administrator' }}
              </td>
              <td class="py-3 px-4">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                  {{ exp.status }}
                </span>
              </td>
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                {{ formatDate(exp.expires_at) }}
              </td>
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400 text-right">
                {{ formatDate(exp.created_at) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Anonymize Modal -->
    <div
      v-if="anonymizeModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <div class="flex items-center gap-2 text-sm font-bold text-rose-600">
            <AlertTriangle class="w-4 h-4" />
            <span>Confirm Irreversible Anonymization</span>
          </div>
          <button @click="anonymizeModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <p class="text-xs text-slate-600 dark:text-slate-300">
          This will permanently overwrite the user's name, email, credentials, and 2FA keys with anonymized hashes while preserving meeting relationship integrity. This action <strong>cannot be undone</strong>.
        </p>

        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Compliance Reason *</label>
          <input
            v-model="anonymizeReason"
            type="text"
            placeholder="e.g. Employee departure / GDPR Article 17 Erasure"
            class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
          />
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
          <button
            @click="anonymizeModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            Cancel
          </button>
          <button
            @click="confirmAnonymize"
            :disabled="anonymizing || !anonymizeReason"
            class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 shadow-md shadow-rose-500/20 transition disabled:opacity-50"
          >
            {{ anonymizing ? 'Redacting...' : 'Permanently Redact User' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import {
  UserX,
  Clock,
  Download,
  Trash2,
  AlertTriangle,
  RefreshCw,
  X,
} from 'lucide-vue-next';

const stats = ref({});
const loading = ref(false);
const feedback = ref('');

const targetUserId = ref(null);
const exporting = ref(false);

const anonymizeModal = ref(false);
const anonymizeReason = ref('');
const anonymizing = ref(false);

const retentionDays = ref(90);
const purging = ref(false);

const fetchStats = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/spa/privacy');
    stats.value = res.data || {};
  } catch (err) {
    console.error('Failed to load privacy stats', err);
  } finally {
    loading.value = false;
  }
};

const exportUser = async () => {
  exporting.value = true;
  try {
    const res = await axios.post('/spa/privacy/export', { user_id: targetUserId.value });
    feedback.value = `User export created: ${res.data?.export?.file_path}`;
    await fetchStats();
  } catch (err) {
    console.error('Failed to export user', err);
  } finally {
    exporting.value = false;
  }
};

const openAnonymizeModal = () => {
  anonymizeReason.value = '';
  anonymizeModal.value = true;
};

const confirmAnonymize = async () => {
  anonymizing.value = true;
  try {
    const res = await axios.post('/spa/privacy/anonymize', {
      user_id: targetUserId.value,
      reason: anonymizeReason.value,
    });
    feedback.value = res.data?.message || 'User anonymized successfully.';
    anonymizeModal.value = false;
    await fetchStats();
  } catch (err) {
    console.error('Failed to anonymize user', err);
  } finally {
    anonymizing.value = false;
  }
};

const purgeRetention = async () => {
  if (!confirm(`Execute retention purge for records older than ${retentionDays.value} days?`)) return;
  purging.value = true;
  try {
    const res = await axios.post('/spa/privacy/purge', { retention_days: retentionDays.value });
    feedback.value = `Retention purge complete. Rows deleted: ${JSON.stringify(res.data?.purged)}`;
    await fetchStats();
  } catch (err) {
    console.error('Failed to purge retention', err);
  } finally {
    purging.value = false;
  }
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleDateString([], { dateStyle: 'short' });
};

onMounted(fetchStats);
</script>
