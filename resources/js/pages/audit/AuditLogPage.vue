<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
            Immutable Audit Trail
          </h1>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20">
            SHA-256 Chained
          </span>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Cryptographically linked security audit trail of all booking, administrative, and host credential operations
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchLogs"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          @click="verifyIntegrity"
          :disabled="verifying"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition disabled:opacity-50"
        >
          <ShieldCheck class="w-4 h-4" />
          <span>{{ verifying ? 'Verifying Chain...' : 'Verify Cryptographic Integrity' }}</span>
        </button>
      </div>
    </div>

    <!-- Verification Result Banner -->
    <div v-if="verificationResult" class="p-4 rounded-2xl flex items-center justify-between" :class="verificationResult.valid ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-800 dark:text-emerald-200' : 'bg-rose-500/10 border border-rose-500/20 text-rose-800 dark:text-rose-200'">
      <div class="flex items-center gap-3">
        <CheckCircle2 v-if="verificationResult.valid" class="w-5 h-5 text-emerald-500 shrink-0" />
        <AlertTriangle v-else class="w-5 h-5 text-rose-500 shrink-0" />
        <div class="text-xs">
          <p class="font-bold">{{ verificationResult.valid ? 'Cryptographic Chain Validated' : 'Cryptographic Integrity Breach Detected' }}</p>
          <p class="opacity-90">
            Verified {{ verificationResult.checked_count }} immutable log block(s).
            {{ verificationResult.valid ? 'All SHA-256 block hashes match historical pointers.' : `Broken pointer detected at block ID: ${verificationResult.broken_at_id}` }}
          </p>
        </div>
      </div>
      <button @click="verificationResult = null" class="text-xs font-semibold hover:underline">Dismiss</button>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-3">
      <div class="flex-1 relative">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="eventFilter"
          @input="debounceFetch"
          type="text"
          placeholder="Filter by event (e.g. meeting.created, user.anonymized)..."
          class="w-full pl-10 pr-4 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
        />
      </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div v-if="loading && !logs.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Reading cryptographic ledger...</p>
      </div>

      <div v-else-if="!logs.length" class="p-12 text-center text-slate-400">
        <ShieldCheck class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No Audit Events Logged</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider w-16">ID</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Event / Action</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Actor</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Origin IP</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">SHA-256 Hash</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Timestamp</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Payload</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
            <tr
              v-for="log in logs"
              :key="log.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition font-mono text-[11px]"
            >
              <td class="py-3 px-4 font-bold text-slate-400">#{{ log.id }}</td>
              <td class="py-3 px-4">
                <span class="px-2 py-0.5 rounded font-bold bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white">
                  {{ log.event }}
                </span>
              </td>
              <td class="py-3 px-4 font-sans text-xs text-slate-700 dark:text-slate-300">
                {{ log.actor?.name || 'System / CLI' }}
              </td>
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                {{ log.ip_address || '127.0.0.1' }}
              </td>
              <td class="py-3 px-4 text-slate-400 max-w-[140px] truncate" :title="log.hash">
                {{ log.hash?.substring(0, 16) }}...
              </td>
              <td class="py-3 px-4 font-sans text-slate-500 dark:text-slate-400">
                {{ formatDate(log.created_at) }}
              </td>
              <td class="py-3 px-4 text-right">
                <button
                  @click="viewDiff(log)"
                  class="p-1 rounded-lg text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                  title="View Change Diff"
                >
                  <Eye class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Payload Diff Modal -->
    <div
      v-if="diffModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4 max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <div class="text-sm font-bold text-slate-900 dark:text-white font-mono">
            Audit Event: {{ selectedLog?.event }} (#{{ selectedLog?.id }})
          </div>
          <button @click="diffModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="space-y-3 text-xs font-mono">
          <div class="p-3 rounded-xl bg-slate-100 dark:bg-slate-800 space-y-1">
            <div><span class="text-slate-400">Actor:</span> {{ selectedLog?.actor?.name || 'System' }} ({{ selectedLog?.actor?.email || 'N/A' }})</div>
            <div><span class="text-slate-400">Target Type:</span> {{ selectedLog?.auditable_type || 'N/A' }}</div>
            <div><span class="text-slate-400">Target ID:</span> {{ selectedLog?.auditable_id || 'N/A' }}</div>
            <div class="truncate"><span class="text-slate-400">Block Hash:</span> {{ selectedLog?.hash }}</div>
            <div class="truncate"><span class="text-slate-400">Previous Pointer:</span> {{ selectedLog?.previous_hash }}</div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <span class="font-bold text-slate-500 uppercase block mb-1">Previous Values</span>
              <pre class="p-3 rounded-xl bg-slate-900 text-slate-300 text-[11px] overflow-x-auto max-h-60">{{ JSON.stringify(selectedLog?.old_values, null, 2) || 'None (Initial State)' }}</pre>
            </div>
            <div>
              <span class="font-bold text-slate-500 uppercase block mb-1">New Values</span>
              <pre class="p-3 rounded-xl bg-slate-900 text-emerald-300 text-[11px] overflow-x-auto max-h-60">{{ JSON.stringify(selectedLog?.new_values, null, 2) || 'None' }}</pre>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end pt-2 border-t border-slate-200/60 dark:border-slate-800/60">
          <button
            @click="diffModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition"
          >
            Close
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
  ShieldCheck,
  CheckCircle2,
  AlertTriangle,
  Search,
  Eye,
  RefreshCw,
  X,
} from 'lucide-vue-next';

const logs = ref([]);
const loading = ref(false);
const verifying = ref(false);
const verificationResult = ref(null);
const eventFilter = ref('');

const diffModal = ref(false);
const selectedLog = ref(null);

let debounceTimer = null;
const debounceFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(fetchLogs, 300);
};

const fetchLogs = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/spa/audit-logs', {
      params: { event: eventFilter.value },
    });
    logs.value = res.data?.data || res.data || [];
  } catch (err) {
    console.error('Failed to load audit logs', err);
  } finally {
    loading.value = false;
  }
};

const verifyIntegrity = async () => {
  verifying.value = true;
  try {
    const res = await axios.post('/spa/audit-logs/verify');
    verificationResult.value = res.data;
  } catch (err) {
    console.error('Failed to verify audit integrity', err);
  } finally {
    verifying.value = false;
  }
};

const viewDiff = (log) => {
  selectedLog.value = log;
  diffModal.value = true;
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleString();
};

onMounted(fetchLogs);
</script>
