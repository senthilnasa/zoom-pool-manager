<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          State Drift Reconciliation
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Detect and resolve state divergence between the local pool ledger and Zoom cloud
        </p>
      </div>

      <div class="flex items-center gap-2">
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
          @click="fetchConflicts"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          @click="runScan"
          :disabled="scanning"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition disabled:opacity-50"
        >
          <GitCompare class="w-4 h-4" :class="{ 'animate-spin': scanning }" />
          <span>{{ scanning ? 'Scanning Zoom...' : 'Trigger Drift Scan' }}</span>
        </button>
      </div>
    </div>

    <!-- Alert / Feedback -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Conflicts List -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div v-if="loading && !conflicts.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Inspecting reconciliation ledger...</p>
      </div>

      <div v-else-if="!conflicts.length" class="p-12 text-center text-slate-400">
        <CheckCircle2 class="w-10 h-10 mx-auto mb-3 text-emerald-500/60" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Clean State & In Sync</p>
        <p class="text-xs text-slate-400 mt-1">No drift conflicts detected between Zoom Cloud and local pool database.</p>
      </div>

      <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
        <div
          v-for="conflict in conflicts"
          :key="conflict.public_id || conflict.id"
          class="p-5 hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition flex flex-col md:flex-row md:items-center justify-between gap-4"
        >
          <div class="space-y-2 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-bold text-sm text-slate-900 dark:text-white">
                {{ conflict.meeting?.title || 'External Zoom Meeting' }}
              </span>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-600 dark:text-amber-400"
              >
                {{ formatConflictType(conflict.conflict_type) }}
              </span>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                :class="conflict.status === 'open' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'"
              >
                {{ conflict.status }}
              </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs p-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/40 dark:border-slate-800/40">
              <div>
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Local Ledger</span>
                <span class="text-slate-700 dark:text-slate-300 font-medium">
                  {{ conflict.local_payload?.starts_at ? formatDate(conflict.local_payload.starts_at) : 'Not in local database' }}
                </span>
                <div v-if="conflict.resource" class="text-[11px] text-slate-400 mt-0.5">
                  Resource: {{ conflict.resource.name }}
                </div>
              </div>
              <div>
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Zoom Cloud</span>
                <span class="text-slate-700 dark:text-slate-300 font-medium">
                  {{ conflict.remote_payload?.start_time ? formatDate(conflict.remote_payload.start_time) : 'Missing in Zoom' }}
                </span>
                <div v-if="conflict.remote_payload?.id" class="text-[11px] text-slate-400 mt-0.5 font-mono">
                  Zoom ID: {{ conflict.remote_payload.id }}
                </div>
              </div>
            </div>

            <div class="flex items-center gap-4 text-[11px] text-slate-400">
              <span>Detected: {{ formatDate(conflict.created_at) }}</span>
              <span v-if="conflict.resolved_by">Resolved by: {{ conflict.resolved_by?.name || 'Administrator' }}</span>
              <span v-if="conflict.resolution_notes" class="italic">Notes: {{ conflict.resolution_notes }}</span>
            </div>
          </div>

          <div v-if="conflict.status === 'open'" class="shrink-0">
            <button
              @click="openResolveModal(conflict)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-brand-50 dark:bg-brand-950/40 text-brand-600 dark:text-brand-400 border border-brand-200 dark:border-brand-800 hover:bg-brand-100 transition shadow-sm"
            >
              <CheckCircle2 class="w-3.5 h-3.5" />
              <span>Resolve Conflict</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Resolve Modal -->
    <div
      v-if="resolveModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <div class="text-sm font-bold text-slate-900 dark:text-white">
            Resolve Drift Conflict
          </div>
          <button @click="resolveModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="space-y-1.5">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Resolution Strategy *</label>
          <select
            v-model="resolveForm.action"
            class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
          >
            <option value="resolve">Push Local State to Zoom Cloud (Overwrite Zoom)</option>
            <option value="accepted_zoom">Accept Zoom State (Update Local Database)</option>
            <option value="marked_external">Mark as External Meeting (Keep in Zoom)</option>
            <option value="ignored">Ignore Conflict</option>
          </select>
        </div>

        <div class="space-y-1.5">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Resolution Notes (Optional)</label>
          <textarea
            v-model="resolveForm.notes"
            rows="3"
            placeholder="Add explanation for resolution audit log..."
            class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200/60 dark:border-slate-800/60">
          <button
            @click="resolveModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            Cancel
          </button>
          <button
            @click="submitResolve"
            :disabled="resolving"
            class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
          >
            {{ resolving ? 'Applying...' : 'Apply Resolution' }}
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
  GitCompare,
  CheckCircle2,
  RefreshCw,
  X,
} from 'lucide-vue-next';

const conflicts = ref([]);
const loading = ref(false);
const scanning = ref(false);
const currentTab = ref('open');
const feedback = ref('');

const resolveModal = ref(false);
const selectedConflict = ref(null);
const resolving = ref(false);
const resolveForm = ref({
  action: 'accepted_zoom',
  notes: '',
});

const tabs = [
  { label: 'Open Conflicts', value: 'open' },
  { label: 'All Conflicts', value: 'all' },
];

const setTab = (tab) => {
  currentTab.value = tab;
  fetchConflicts();
};

const fetchConflicts = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/admin/drift', {
      params: { status: currentTab.value },
      headers: { Accept: 'application/json' },
    });
    conflicts.value = res.data?.data || res.data || [];
  } catch (err) {
    console.error('Failed to load drift conflicts', err);
  } finally {
    loading.value = false;
  }
};

const runScan = async () => {
  scanning.value = true;
  feedback.value = '';
  try {
    const res = await axios.post('/admin/drift/scan', { days: 30 }, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = `Scan completed. Checked ${res.data?.checked_resources || 0} resource(s), ${res.data?.conflicts_created || 0} new conflict(s) found.`;
    await fetchConflicts();
  } catch (err) {
    console.error('Failed to run drift scan', err);
  } finally {
    scanning.value = false;
  }
};

const openResolveModal = (conflict) => {
  selectedConflict.value = conflict;
  resolveForm.value = { action: 'accepted_zoom', notes: '' };
  resolveModal.value = true;
};

const submitResolve = async () => {
  resolving.value = true;
  try {
    await axios.post(`/admin/drift/${selectedConflict.value.public_id}/resolve`, resolveForm.value, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = 'Conflict resolved successfully.';
    resolveModal.value = false;
    await fetchConflicts();
  } catch (err) {
    console.error('Failed to resolve conflict', err);
  } finally {
    resolving.value = false;
  }
};

const formatConflictType = (type) => {
  if (!type) return 'Unknown';
  return type.replace(/_/g, ' ');
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleString();
};

onMounted(fetchConflicts);
</script>
