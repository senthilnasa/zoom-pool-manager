<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Operational Alerts
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Real-time anomaly detection, synchronization failures, and system alerts
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <div class="relative w-48 sm:w-60">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search alerts..."
            class="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
          />
        </div>

        <!-- Status Filter tabs -->
        <div class="inline-flex rounded-xl bg-slate-100 dark:bg-slate-800 p-1 border border-slate-200/60 dark:border-slate-700/60">
          <button
            v-for="tab in tabs"
            :key="tab.value"
            @click="setTab(tab.value)"
            class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all"
            :class="currentTab === tab.value ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
          >
            {{ tab.label }}
          </button>
        </div>

        <button
          @click="fetchAlerts"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>
      </div>
    </div>

    <!-- Feedback Message -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Alert Cards / Table -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div v-if="loading && !alerts.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Loading operational alerts...</p>
      </div>

      <div v-else-if="!filteredAlerts.length" class="p-12 text-center text-slate-400">
        <ShieldAlert class="w-10 h-10 mx-auto mb-3 text-emerald-500/60" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No Matching Alerts' : 'All Systems Operational' }}
        </p>
        <p class="text-xs text-slate-400 mt-1">
          {{ searchQuery ? 'Try adjusting your search criteria.' : `No ${currentTab !== 'all' ? currentTab : ''} alerts matching criteria.` }}
        </p>
      </div>

      <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
        <div
          v-for="alert in filteredAlerts"
          :key="alert.public_id || alert.id"
          class="p-5 hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition flex flex-col md:flex-row md:items-center justify-between gap-4"
        >
          <div class="flex items-start gap-3.5">
            <div
              class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
              :class="getSeverityClass(alert.severity)"
            >
              <AlertTriangle v-if="alert.severity === 'critical' || alert.severity === 'high'" class="w-5 h-5 text-rose-600 dark:text-rose-400" />
              <Info v-else class="w-5 h-5 text-amber-600 dark:text-amber-400" />
            </div>

            <div class="space-y-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-bold text-sm text-slate-900 dark:text-white">
                  {{ alert.title }}
                </span>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="getSeverityBadgeClass(alert.severity)"
                >
                  {{ alert.severity }}
                </span>
                <span
                  v-if="alert.resolved_at"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                >
                  Resolved
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-500/10 text-rose-600 dark:text-rose-400 animate-pulse"
                >
                  Active
                </span>
              </div>

              <p class="text-xs text-slate-600 dark:text-slate-300">
                {{ alert.message || alert.description }}
              </p>

              <div class="flex items-center gap-4 text-[11px] text-slate-400 pt-1">
                <span>Key: <code class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-800 rounded font-mono text-[10px]">{{ alert.key }}</code></span>
                <span>Occurrences: <strong class="text-slate-600 dark:text-slate-300">{{ alert.count || 1 }}</strong></span>
                <span>Last seen: {{ formatDate(alert.last_seen_at) }}</span>
                <span v-if="alert.resolution_notes" class="italic text-emerald-600 dark:text-emerald-400">Note: {{ alert.resolution_notes }}</span>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0 self-end md:self-center">
            <button
              v-if="!alert.resolved_at"
              @click="resolveAlert(alert)"
              :disabled="resolvingId === (alert.public_id || alert.id)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 transition shadow-sm"
            >
              <CheckCircle2 class="w-3.5 h-3.5" />
              <span>{{ resolvingId === (alert.public_id || alert.id) ? 'Resolving...' : 'Mark Resolved' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import {
  ShieldAlert,
  AlertTriangle,
  Info,
  CheckCircle2,
  RefreshCw,
  Search,
} from 'lucide-vue-next';

const alerts = ref([]);
const loading = ref(false);
const currentTab = ref('open');
const feedback = ref('');
const resolvingId = ref(null);
const searchQuery = ref('');

const filteredAlerts = computed(() => {
  if (!searchQuery.value.trim()) return alerts.value;
  const q = searchQuery.value.toLowerCase().trim();
  return alerts.value.filter((a) => {
    return (
      (a.title && a.title.toLowerCase().includes(q)) ||
      (a.message && a.message.toLowerCase().includes(q)) ||
      (a.component && a.component.toLowerCase().includes(q)) ||
      (a.severity && a.severity.toLowerCase().includes(q))
    );
  });
});

const tabs = [
  { label: 'Active Alerts', value: 'open' },
  { label: 'Resolved', value: 'resolved' },
  { label: 'All Alerts', value: 'all' },
];

const setTab = (tab) => {
  currentTab.value = tab;
  fetchAlerts();
};

const fetchAlerts = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/admin/alerts', {
      params: { status: currentTab.value },
      headers: { Accept: 'application/json' },
    });
    alerts.value = res.data?.alerts?.data || res.data?.alerts || [];
  } catch (err) {
    console.error('Failed to load alerts', err);
  } finally {
    loading.value = false;
  }
};

const resolveAlert = async (alert) => {
  const id = alert.public_id || alert.id;
  resolvingId.value = id;
  try {
    const res = await axios.post(`/admin/alerts/${id}/resolve`, {}, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = res.data?.message || 'Alert marked as resolved.';
    await fetchAlerts();
  } catch (err) {
    console.error('Failed to resolve alert', err);
  } finally {
    resolvingId.value = null;
  }
};

const getSeverityClass = (sev) => {
  if (sev === 'critical' || sev === 'high') return 'bg-rose-500/10 text-rose-600';
  if (sev === 'warning') return 'bg-amber-500/10 text-amber-600';
  return 'bg-blue-500/10 text-blue-600';
};

const getSeverityBadgeClass = (sev) => {
  if (sev === 'critical' || sev === 'high') return 'bg-rose-500/10 text-rose-600 dark:text-rose-400';
  if (sev === 'warning') return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
  return 'bg-blue-500/10 text-blue-600 dark:text-blue-400';
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleString();
};

onMounted(fetchAlerts);
</script>
