<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">
          Recurring Meeting Series
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Review recurring schedules, single-resource lockouts, and multi-date occurrence allocations.
        </p>
      </div>

      <router-link
        to="/app/meetings/create"
        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold shadow-md shadow-brand-500/20 transition-all"
      >
        <Plus class="w-4 h-4" />
        <span>New Series</span>
      </router-link>
    </div>

    <!-- Filters and Search -->
    <div class="flex items-center gap-3">
      <div class="relative flex-1 max-w-md">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by series ID, mode, rule, or status..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Series Table -->
    <GlassCard :padding="false">
      <div v-if="loading && seriesList.length === 0" class="py-16 text-center text-sm text-slate-400">
        Loading recurring series...
      </div>

      <div v-else-if="filteredSeriesList.length === 0" class="py-16 text-center">
        <Repeat class="w-10 h-10 text-slate-300 dark:text-slate-700 mx-auto mb-3" />
        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No Matching Series Found' : 'No recurring series found' }}
        </div>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
          {{ searchQuery ? 'Try adjusting your search criteria.' : 'Schedule recurring classes or department syncs with automatic conflict prevention and occurrence management.' }}
        </p>
        <router-link
          v-if="!searchQuery"
          to="/app/meetings/create"
          class="inline-block mt-4 text-xs font-semibold text-brand-600 dark:text-brand-400"
        >
          + Schedule New Meeting Series
        </router-link>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
              <th class="py-3.5 px-4">Series ID / Mode</th>
              <th class="py-3.5 px-4">Recurrence Rule</th>
              <th class="py-3.5 px-4">Date Range</th>
              <th class="py-3.5 px-4">Occurrences</th>
              <th class="py-3.5 px-4">Status</th>
              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
            <tr
              v-for="s in filteredSeriesList"
              :key="s.public_id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors"
            >
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 dark:text-white font-mono text-xs">
                  {{ s.public_id }}
                </div>
                <div class="text-xs text-slate-400 mt-0.5">
                  {{ s.series_mode || 'FLEXIBLE_POOLED' }}
                </div>
              </td>
              <td class="py-3.5 px-4 text-xs font-mono text-slate-600 dark:text-slate-300">
                {{ s.rrule || 'FREQ=WEEKLY' }}
              </td>
              <td class="py-3.5 px-4 text-xs text-slate-500">
                {{ s.start_date || 'N/A' }} &rarr; {{ s.until_date || 'Ongoing' }}
              </td>
              <td class="py-3.5 px-4 text-xs">
                <span class="font-semibold text-slate-800 dark:text-slate-200">
                  {{ s.meetings_count || s.occurrence_count || 0 }}
                </span>
                <span class="text-slate-400 text-[11px]"> sessions</span>
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="glass-badge"
                  :class="{
                    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20': s.status === 'active',
                    'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20': s.status === 'completed',
                    'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20': s.status === 'cancelled',
                  }"
                >
                  {{ s.status }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right">
                <button
                  v-if="s.status !== 'cancelled'"
                  @click="cancelSeries(s)"
                  class="px-2.5 py-1 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/60 text-xs font-semibold transition-colors"
                >
                  Cancel Series
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </GlassCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import GlassCard from '@/components/GlassCard.vue';
import { useToastStore } from '@/stores/toast';
import { Repeat, Plus, Search } from 'lucide-vue-next';

const toast = useToastStore();
const seriesList = ref([]);
const loading = ref(false);
const searchQuery = ref('');

const filteredSeriesList = computed(() => {
  if (!searchQuery.value.trim()) return seriesList.value;
  const q = searchQuery.value.toLowerCase().trim();
  return seriesList.value.filter((s) => {
    return (
      (s.public_id && s.public_id.toLowerCase().includes(q)) ||
      (s.series_mode && s.series_mode.toLowerCase().includes(q)) ||
      (s.rrule && s.rrule.toLowerCase().includes(q)) ||
      (s.status && s.status.toLowerCase().includes(q))
    );
  });
});

const loadSeries = async (page = 1) => {
  try {
    loading.value = true;
    const res = await axios.get('/spa/series', { params: { page } });
    seriesList.value = res.data.data;
  } catch (e) {
    console.error('Failed to load series', e);
  } finally {
    loading.value = false;
  }
};

const cancelSeries = async (series) => {
  if (!confirm(`Cancel recurring series ${series.public_id}? All future occurrences will be cancelled.`)) return;
  try {
    await axios.post(`/series/${series.public_id}/cancel`);
    toast.success('Series cancelled successfully.');
    loadSeries(1);
  } catch (e) {
    toast.error('Failed to cancel series.');
  }
};

onMounted(() => {
  loadSeries(1);
});
</script>
