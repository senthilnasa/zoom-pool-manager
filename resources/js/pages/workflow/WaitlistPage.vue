<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Meeting Waitlist Queue
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Priority-ordered waitlist queue for slots oversubscribed during peak booking intervals
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchWaitlist"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          v-if="authStore.isAdmin"
          @click="promoteNext"
          :disabled="promoting || !entries.length"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition disabled:opacity-50"
        >
          <ArrowUpCircle class="w-4 h-4" />
          <span>{{ promoting ? 'Allocating...' : 'Allocate Next Eligible' }}</span>
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
          placeholder="Search by meeting title, ID, requester, or pool..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Alert / Feedback -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Waitlist Queue Table -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div v-if="loading && !entries.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Polling waitlist queue...</p>
      </div>

      <div v-else-if="!filteredEntries.length" class="p-12 text-center text-slate-400">
        <CheckCircle2 class="w-10 h-10 mx-auto mb-3 text-emerald-500/60" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No Matching Waitlist Entries' : 'Waitlist Queue Is Clear' }}
        </p>
        <p class="text-xs text-slate-400 mt-1">
          {{ searchQuery ? 'Try adjusting your search query.' : 'All requested sessions have been successfully fulfilled by pool resources.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider w-20">Priority</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Target Session</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Host / Requester</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Requested Horizon</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
            <tr
              v-for="entry in filteredEntries"
              :key="entry.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
            >
              <td class="py-3.5 px-4 font-bold text-center">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-800 text-brand-600 dark:text-brand-400 font-mono text-xs">
                  {{ entry.priority }}
                </span>
              </td>
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 dark:text-white">{{ entry.meeting?.title }}</div>
                <div class="text-[11px] text-slate-400 font-mono">ID: {{ entry.meeting?.public_id }}</div>
              </td>
              <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                <div>{{ entry.meeting?.owner?.name || entry.meeting?.requester?.name || 'User' }}</div>
                <div class="text-[11px] text-slate-400">{{ entry.meeting?.owner?.email }}</div>
              </td>
              <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400">
                <div>{{ formatDate(entry.meeting?.starts_at) }}</div>
                <div class="text-[11px] text-slate-400">Duration: {{ entry.meeting?.duration_minutes }}m</div>
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="entry.status === 'waiting' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 animate-pulse' : 'bg-slate-500/10 text-slate-500'"
                >
                  {{ entry.status }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button
                    v-if="entry.status === 'waiting' && authStore.isAdmin"
                    @click="promoteEntry(entry)"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 transition shadow-sm"
                  >
                    <CheckCircle2 class="w-3.5 h-3.5" />
                    <span>Promote</span>
                  </button>
                  <button
                    v-if="entry.status === 'waiting' && authStore.isAdmin"
                    @click="cancelEntry(entry)"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition"
                  >
                    Cancel
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import {
  ArrowUpCircle,
  CheckCircle2,
  RefreshCw,
  Search,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const entries = ref([]);
const loading = ref(false);
const promoting = ref(false);
const feedback = ref('');
const searchQuery = ref('');

const filteredEntries = computed(() => {
  if (!searchQuery.value.trim()) return entries.value;
  const q = searchQuery.value.toLowerCase().trim();
  return entries.value.filter((e) => {
    return (
      (e.meeting?.title && e.meeting.title.toLowerCase().includes(q)) ||
      (e.meeting?.public_id && e.meeting.public_id.toLowerCase().includes(q)) ||
      (e.user?.name && e.user.name.toLowerCase().includes(q)) ||
      (e.user?.email && e.user.email.toLowerCase().includes(q)) ||
      (e.pool?.name && e.pool.name.toLowerCase().includes(q))
    );
  });
});

const fetchWaitlist = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/spa/waitlist');
    entries.value = res.data || [];
  } catch (err) {
    console.error('Failed to load waitlist', err);
  } finally {
    loading.value = false;
  }
};

const promoteNext = async () => {
  if (!entries.value.length) return;
  promoting.value = true;
  try {
    const res = await axios.post(`/spa/waitlist/${entries.value[0].id}/promote`);
    if (res.data?.allocated_meeting) {
      feedback.value = `Allocated resource for meeting "${res.data.allocated_meeting.title}".`;
    } else {
      feedback.value = 'No idle pool resource was available at this moment for allocation.';
    }
    await fetchWaitlist();
  } catch (err) {
    console.error('Failed to promote waitlist entry', err);
  } finally {
    promoting.value = false;
  }
};

const promoteEntry = async (entry) => {
  try {
    const res = await axios.post(`/spa/waitlist/${entry.id}/promote`);
    feedback.value = res.data?.allocated_meeting
      ? `Allocated meeting "${res.data.allocated_meeting.title}".`
      : 'Attempted promotion. Next available slot checked.';
    await fetchWaitlist();
  } catch (err) {
    console.error('Failed to promote entry', err);
  }
};

const cancelEntry = async (entry) => {
  if (!confirm(`Cancel waitlist entry for "${entry.meeting?.title}"?`)) return;
  try {
    await axios.post(`/spa/waitlist/${entry.id}/cancel`);
    feedback.value = 'Waitlist entry cancelled.';
    await fetchWaitlist();
  } catch (err) {
    console.error('Failed to cancel waitlist entry', err);
  }
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
};

onMounted(fetchWaitlist);
</script>
