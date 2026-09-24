<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Workflow Rules Builder
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Priority-based policies for auto-approval, rejection, and recording enforcement
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="showSimulateModal = true"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold bg-white/80 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition shadow-sm"
        >
          <Play class="w-3.5 h-3.5 text-amber-500" />
          <span>Simulate Engine</span>
        </button>

        <button
          v-if="authStore.can('workflow.manage') || authStore.isAdmin"
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Plus class="w-4 h-4" />
          <span>New Rule</span>
        </button>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="flex items-center gap-3">
      <div class="relative flex-1 max-w-md">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search rules by name, priority, or condition..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Alert status message if any -->
    <div v-if="statusMessage" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ statusMessage }}</span>
      <button @click="statusMessage = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Rules Table -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div v-if="loading && !rules.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Loading workflow rules...</p>
      </div>

      <div v-else-if="!filteredRules.length" class="p-12 text-center text-slate-400">
        <GitMerge class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-medium">
          {{ searchQuery ? 'No workflow rules matching search criteria.' : 'No workflow rules configured yet.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider w-24">Priority</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Rule Name</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Conditions</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Actions</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider w-24">Status</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right w-24">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <tr v-for="rule in filteredRules" :key="rule.id" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
              <td class="py-4 px-4 font-mono font-bold text-xs text-brand-600 dark:text-brand-400">
                #{{ rule.priority }}
              </td>
              <td class="py-4 px-4 font-semibold text-slate-900 dark:text-white">
                {{ rule.name }}
              </td>
              <td class="py-4 px-4 text-xs font-mono text-slate-600 dark:text-slate-300">
                <div class="truncate max-w-xs">{{ formatJsonSummary(rule.conditions) }}</div>
              </td>
              <td class="py-4 px-4 text-xs font-mono text-slate-600 dark:text-slate-300">
                <div class="truncate max-w-xs">{{ formatJsonSummary(rule.actions) }}</div>
              </td>
              <td class="py-4 px-4">
                <button
                  @click="toggleRule(rule)"
                  :disabled="!authStore.can('workflow.manage') && !authStore.isAdmin"
                  class="px-2.5 py-1 rounded-full text-[11px] font-bold transition flex items-center gap-1.5"
                  :class="rule.is_enabled ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-slate-500/10 text-slate-500 dark:text-slate-400 border border-slate-500/20'"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="rule.is_enabled ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                  <span>{{ rule.is_enabled ? 'Enabled' : 'Disabled' }}</span>
                </button>
              </td>
              <td class="py-4 px-4 text-right">
                <button
                  v-if="authStore.can('workflow.manage') || authStore.isAdmin"
                  @click="deleteRule(rule)"
                  class="p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition"
                  title="Delete Rule"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Rule Modal -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
      @click.self="showCreateModal = false"
    >
      <div class="glass-card max-w-lg w-full p-6 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-2xl bg-white dark:bg-slate-900 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <h3 class="font-bold text-base text-slate-900 dark:text-white">Create Workflow Rule</h3>
          <button @click="showCreateModal = false" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitRule" class="space-y-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Rule Name</label>
            <input
              v-model="form.name"
              required
              placeholder="e.g., Auto-Approve Short Faculty Lectures"
              class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Priority (1 = Highest)</label>
            <input
              v-model.number="form.priority"
              type="number"
              min="1"
              max="1000"
              required
              class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
            />
          </div>

          <!-- Conditions Builder -->
          <div class="p-3.5 rounded-2xl bg-slate-50/80 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60 space-y-2">
            <h4 class="font-bold text-slate-800 dark:text-slate-200">Match Conditions</h4>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[10px] uppercase tracking-wider text-slate-400 mb-0.5">Meeting Type</label>
                <input
                  v-model="form.conditions.meeting_type"
                  placeholder="e.g., webinar, exam"
                  class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                />
              </div>
              <div>
                <label class="block text-[10px] uppercase tracking-wider text-slate-400 mb-0.5">Min Duration (min)</label>
                <input
                  v-model.number="form.conditions.duration_min"
                  type="number"
                  placeholder="e.g., 120"
                  class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                />
              </div>
            </div>
          </div>

          <!-- Actions Builder -->
          <div class="p-3.5 rounded-2xl bg-slate-50/80 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60 space-y-2">
            <h4 class="font-bold text-slate-800 dark:text-slate-200">Enforcement Actions</h4>
            <div class="space-y-2">
              <label class="flex items-center gap-2">
                <input type="checkbox" v-model="form.actions.auto_approve" class="rounded text-brand-600" />
                <span class="font-medium text-slate-700 dark:text-slate-300">Auto Approve Instantly</span>
              </label>
              <div>
                <label class="block text-[10px] uppercase tracking-wider text-slate-400 mb-0.5">Or Reject with Reason</label>
                <input
                  v-model="form.actions.reject"
                  placeholder="e.g., Exams require Dean approval."
                  class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                />
              </div>
            </div>
          </div>

          <div class="pt-3 flex justify-end gap-2">
            <button
              type="button"
              @click="showCreateModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white transition"
            >
              Save Rule
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Rule Simulator Modal -->
    <div
      v-if="showSimulateModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
      @click.self="showSimulateModal = false"
    >
      <div class="glass-card max-w-lg w-full p-6 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-2xl bg-white dark:bg-slate-900 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2">
            <Play class="w-4 h-4 text-amber-500" />
            <h3 class="font-bold text-base text-slate-900 dark:text-white">Rule Engine Simulator</h3>
          </div>
          <button @click="showSimulateModal = false" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="runSimulation" class="space-y-3 text-xs">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-medium text-slate-500 mb-1">Meeting Type</label>
              <input v-model="simData.meeting_type" placeholder="e.g., webinar, exam" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700" />
            </div>
            <div>
              <label class="block font-medium text-slate-500 mb-1">Duration (minutes)</label>
              <input v-model.number="simData.duration_minutes" type="number" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700" />
            </div>
          </div>

          <button
            type="submit"
            :disabled="simulating"
            class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold transition flex items-center justify-center gap-2"
          >
            <Play class="w-3.5 h-3.5" :class="{ 'animate-spin': simulating }" />
            <span>Evaluate Rules Against Draft</span>
          </button>
        </form>

        <div v-if="simResult" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 space-y-2 text-xs">
          <div class="flex items-center justify-between">
            <span class="font-bold text-slate-800 dark:text-slate-200">Outcome:</span>
            <span v-if="simResult.is_rejected" class="px-2 py-0.5 rounded-full font-bold bg-rose-500/10 text-rose-500 border border-rose-500/20">REJECTED</span>
            <span v-else-if="simResult.is_auto_approved" class="px-2 py-0.5 rounded-full font-bold bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">AUTO-APPROVED</span>
            <span v-else class="px-2 py-0.5 rounded-full font-bold bg-brand-500/10 text-brand-500 border border-brand-500/20">MANUAL APPROVAL REQUIRED</span>
          </div>
          <div v-if="simResult.reject_reason" class="text-rose-500 font-medium">
            Reason: {{ simResult.reject_reason }}
          </div>
          <div class="text-[11px] text-slate-400">
            Rules Matched: {{ simResult.matched_count }} ({{ (simResult.matched_rules || []).map(r => r.name).join(', ') || 'None' }})
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import {
  GitMerge,
  Play,
  Plus,
  RefreshCw,
  Search,
  Trash2,
  X,
} from 'lucide-vue-next';

const authStore = useAuthStore();

const loading = ref(false);
const saving = ref(false);
const simulating = ref(false);
const rules = ref([]);
const statusMessage = ref('');
const searchQuery = ref('');

const filteredRules = computed(() => {
  if (!searchQuery.value.trim()) return rules.value;
  const q = searchQuery.value.toLowerCase().trim();
  return rules.value.filter((r) => {
    return (
      (r.name && r.name.toLowerCase().includes(q)) ||
      (String(r.priority).includes(q)) ||
      (JSON.stringify(r.conditions || {}).toLowerCase().includes(q)) ||
      (JSON.stringify(r.actions || {}).toLowerCase().includes(q))
    );
  });
});

const showCreateModal = ref(false);
const showSimulateModal = ref(false);

const form = ref({
  name: '',
  priority: 50,
  conditions: {},
  actions: {},
  is_enabled: true,
});

const simData = ref({
  meeting_type: 'exam',
  duration_minutes: 180,
  participant_count: 50,
});
const simResult = ref(null);

const fetchRules = async () => {
  try {
    loading.value = true;
    const res = await axios.get('/admin/workflows');
    rules.value = res.data.rules || [];
  } catch (err) {
    console.error('Failed to load rules', err);
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  form.value = {
    name: '',
    priority: 50,
    conditions: {},
    actions: {},
    is_enabled: true,
  };
  showCreateModal.value = true;
};

const submitRule = async () => {
  try {
    saving.value = true;
    await axios.post('/admin/workflows', form.value);
    showCreateModal.value = false;
    statusMessage.value = 'Rule created successfully.';
    await fetchRules();
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to create rule.');
  } finally {
    saving.value = false;
  }
};

const toggleRule = async (rule) => {
  try {
    await axios.post(`/admin/workflows/${rule.public_id}/toggle`);
    rule.is_enabled = !rule.is_enabled;
  } catch (err) {
    console.error('Failed to toggle rule', err);
  }
};

const deleteRule = async (rule) => {
  if (!confirm(`Delete workflow rule "${rule.name}"?`)) return;
  try {
    await axios.delete(`/admin/workflows/${rule.public_id}`);
    rules.value = rules.value.filter((r) => r.id !== rule.id);
  } catch (err) {
    alert('Failed to delete rule.');
  }
};

const runSimulation = async () => {
  try {
    simulating.value = true;
    const res = await axios.post('/admin/workflows/simulate', simData.value);
    simResult.value = res.data;
  } catch (err) {
    alert('Simulation error.');
  } finally {
    simulating.value = false;
  }
};

const formatJsonSummary = (obj) => {
  if (!obj) return 'None';
  return Object.entries(obj)
    .filter(([_, v]) => v !== null && v !== '' && v !== false)
    .map(([k, v]) => `${k}: ${v}`)
    .join(', ') || 'None';
};

onMounted(() => {
  fetchRules();
});
</script>
