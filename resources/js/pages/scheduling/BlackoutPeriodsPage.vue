<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Institutional Blackout Windows
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Campus holidays, examination windows, and maintenance blackouts blocking automated meeting scheduling
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchBlackouts"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          v-if="authStore.isAdmin"
          @click="showCreateModal = true"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Plus class="w-4 h-4" />
          <span>New Blackout Period</span>
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
          placeholder="Search by window title, classification, or reason..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Alert / Feedback -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Blackouts List -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div v-if="loading && !blackouts.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Loading blackout calendar...</p>
      </div>

      <div v-else-if="!filteredBlackouts.length" class="p-12 text-center text-slate-400">
        <CalendarX class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No Matching Blackout Periods' : 'No Blackout Periods Scheduled' }}
        </p>
        <p class="text-xs text-slate-400 mt-1">
          {{ searchQuery ? 'Try adjusting your search criteria.' : 'Resource allocation is available across all dates and times.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Window Title</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Classification</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Department Scope</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Time Horizon</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Reason</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
            <tr
              v-for="b in filteredBlackouts"
              :key="b.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
            >
              <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                {{ b.name }}
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="getTypeBadge(b.type)"
                >
                  {{ b.type }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400">
                {{ b.department?.name || 'All Campus (Global)' }}
              </td>
              <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400">
                <div>From: {{ formatDate(b.starts_at) }}</div>
                <div>To: {{ formatDate(b.ends_at) }}</div>
              </td>
              <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 max-w-xs truncate" :title="b.reason">
                {{ b.reason || '—' }}
              </td>
              <td class="py-3.5 px-4 text-right">
                <button
                  v-if="authStore.isAdmin"
                  @click="deleteBlackout(b)"
                  class="px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition"
                >
                  Remove
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Modal -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">New Blackout Period</h2>
          <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="saveBlackout" class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Period Name *</label>
            <input
              v-model="createForm.name"
              type="text"
              required
              placeholder="e.g. End Semester Exam Window"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Classification *</label>
            <select
              v-model="createForm.type"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option value="exam">Examination Window</option>
              <option value="holiday">University Holiday</option>
              <option value="maintenance">IT Maintenance / Upgrade</option>
              <option value="institutional">Institutional Event</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Department Scope</label>
            <select
              v-model="createForm.department_id"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option :value="null">Global (Entire Organization)</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Starts At *</label>
              <input
                v-model="createForm.starts_at"
                type="datetime-local"
                required
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ends At *</label>
              <input
                v-model="createForm.ends_at"
                type="datetime-local"
                required
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Reason / Justification</label>
            <textarea
              v-model="createForm.reason"
              rows="2"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
            <button
              type="button"
              @click="showCreateModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
            >
              {{ saving ? 'Scheduling...' : 'Set Blackout Window' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import {
  CalendarX,
  Plus,
  RefreshCw,
  Search,
  X,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const blackouts = ref([]);
const departments = ref([]);
const loading = ref(false);
const saving = ref(false);
const feedback = ref('');
const searchQuery = ref('');

const filteredBlackouts = computed(() => {
  if (!searchQuery.value.trim()) return blackouts.value;
  const q = searchQuery.value.toLowerCase().trim();
  return blackouts.value.filter((b) => {
    return (
      (b.name && b.name.toLowerCase().includes(q)) ||
      (b.type && b.type.toLowerCase().includes(q)) ||
      (b.reason && b.reason.toLowerCase().includes(q)) ||
      (b.department?.name && b.department.name.toLowerCase().includes(q))
    );
  });
});

const showCreateModal = ref(false);
const createForm = ref({
  name: '',
  type: 'exam',
  department_id: null,
  starts_at: '',
  ends_at: '',
  reason: '',
});

const fetchBlackouts = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/spa/blackouts');
    blackouts.value = res.data?.blackouts || [];
    departments.value = res.data?.departments || [];
  } catch (err) {
    console.error('Failed to load blackout periods', err);
  } finally {
    loading.value = false;
  }
};

const saveBlackout = async () => {
  saving.value = true;
  try {
    await axios.post('/spa/blackouts', createForm.value);
    feedback.value = `Blackout window "${createForm.value.name}" established.`;
    showCreateModal.value = false;
    createForm.value = {
      name: '',
      type: 'exam',
      department_id: null,
      starts_at: '',
      ends_at: '',
      reason: '',
    };
    await fetchBlackouts();
  } catch (err) {
    console.error('Failed to save blackout', err);
  } finally {
    saving.value = false;
  }
};

const deleteBlackout = async (b) => {
  if (!confirm(`Remove blackout window "${b.name}"?`)) return;
  try {
    await axios.delete(`/spa/blackouts/${b.id}`);
    feedback.value = 'Blackout window removed.';
    await fetchBlackouts();
  } catch (err) {
    console.error('Failed to delete blackout', err);
  }
};

const getTypeBadge = (type) => {
  if (type === 'exam') return 'bg-rose-500/10 text-rose-600 dark:text-rose-400';
  if (type === 'maintenance') return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
  if (type === 'holiday') return 'bg-purple-500/10 text-purple-600 dark:text-purple-400';
  return 'bg-blue-500/10 text-blue-600 dark:text-blue-400';
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
};

onMounted(fetchBlackouts);
</script>
