<template>
  <div class="space-y-6">
    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
          Welcome back, {{ authStore.user?.name || 'User' }}
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          {{ stats.is_admin ? 'High-performance resource scheduling & Zoom license optimization pool.' : 'Submit and monitor your Zoom meeting requests, active sessions, and booking schedule.' }}
        </p>
      </div>

      <div class="flex items-center gap-3">
        <router-link
          to="/app/meetings/create"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold shadow-md shadow-brand-500/20 transition-all hover:scale-[1.02] active:scale-[0.98]"
        >
          <Plus class="w-4 h-4" />
          <span>{{ stats.is_admin ? 'Book Meeting' : 'Request Meeting' }}</span>
        </router-link>

        <button
          @click="fetchStats"
          :disabled="loading"
          class="p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white/60 dark:bg-slate-900/60 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-all"
          title="Refresh Data"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>
      </div>
    </div>

    <!-- Metric Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <!-- Meetings Today -->
      <GlassCard :padding="true" customClass="relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            {{ stats.is_admin ? 'Meetings Today' : 'My Meetings Today' }}
          </div>
          <div class="p-2 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
            <Calendar class="w-5 h-5" />
          </div>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-900 dark:text-white">
            {{ stats.meetings_today }}
          </span>
          <span class="text-xs text-slate-500">{{ stats.is_admin ? 'scheduled' : 'today' }}</span>
        </div>
      </GlassCard>

      <!-- Active Meetings -->
      <GlassCard :padding="true" customClass="relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            {{ stats.is_admin ? 'Active Now' : 'My Active Sessions' }}
          </div>
          <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
            <Radio class="w-5 h-5 animate-pulse" />
          </div>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-900 dark:text-white">
            {{ stats.active_meetings }}
          </span>
          <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">live sessions</span>
        </div>
      </GlassCard>

      <!-- Card 3: Pool Resources (Admin) OR My Pending Requests (User) -->
      <GlassCard v-if="stats.is_admin" :padding="true" customClass="relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Pool Resources
          </div>
          <div class="p-2 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
            <Server class="w-5 h-5" />
          </div>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-900 dark:text-white">
            {{ stats.active_licenses }}
          </span>
          <span class="text-xs text-slate-500">/ {{ stats.total_licenses }} licenses active</span>
        </div>
      </GlassCard>

      <GlassCard v-else :padding="true" customClass="relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Pending Requests
          </div>
          <div class="p-2 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
            <Clock class="w-5 h-5" />
          </div>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-900 dark:text-white">
            {{ stats.my_pending_requests }}
          </span>
          <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">awaiting review</span>
        </div>
      </GlassCard>

      <!-- Card 4: Approvals Queue (Admin) OR Confirmed Bookings (User) -->
      <GlassCard v-if="stats.is_admin" :padding="true" customClass="relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Approvals Queue
          </div>
          <div class="p-2 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
            <Clock class="w-5 h-5" />
          </div>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-900 dark:text-white">
            {{ stats.pending_approvals }}
          </span>
          <span class="text-xs text-slate-500">requests waiting</span>
        </div>
      </GlassCard>

      <GlassCard v-else :padding="true" customClass="relative overflow-hidden">
        <div class="flex items-center justify-between">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Confirmed Bookings
          </div>
          <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
            <CheckCircle2 class="w-5 h-5" />
          </div>
        </div>
        <div class="mt-4 flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-900 dark:text-white">
            {{ stats.my_approved_meetings }}
          </span>
          <span class="text-xs text-slate-500">of {{ stats.my_total_requests }} total requests</span>
        </div>
      </GlassCard>
    </div>

    <!-- Active Live Sessions Monitor (Visible when sessions are in progress) -->
    <GlassCard
      v-if="activeMeetingsList.length > 0"
      customClass="border-emerald-500/30 dark:border-emerald-500/20 bg-emerald-500/5 dark:bg-emerald-950/10"
    >
      <div class="flex items-center justify-between pb-3 border-b border-emerald-500/20">
        <div class="flex items-center gap-2.5">
          <span class="relative flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
          </span>
          <h3 class="text-sm font-extrabold text-emerald-900 dark:text-emerald-200">
            Active Live Zoom Sessions ({{ activeMeetingsList.length }})
          </h3>
        </div>
        <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
          Pooled Licenses Occupied & Broadcasting
        </span>
      </div>

      <div class="mt-3 divide-y divide-emerald-500/10">
        <div
          v-for="m in activeMeetingsList"
          :key="m.public_id"
          class="py-3 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs"
        >
          <div class="space-y-1">
            <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <span>{{ m.title }}</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">
                Running for {{ m.elapsed_minutes }}m
              </span>
            </div>
            <div class="text-slate-500 dark:text-slate-400 flex items-center gap-3">
              <span>Host: <strong class="text-slate-700 dark:text-slate-300">{{ m.owner_name }}</strong></span>
              <span>•</span>
              <span>Account: {{ m.resource_name }}</span>
              <span>•</span>
              <span>Dept: {{ m.department_name }}</span>
              <span v-if="m.zoom_meeting_id">• ID: <span class="font-mono">{{ m.zoom_meeting_id }}</span></span>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <a
              :href="`/spa/meetings/${m.public_id}/ics`"
              download
              class="p-2 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 text-slate-700 dark:text-slate-200 transition"
              title="Download .ICS Calendar"
            >
              <Download class="w-3.5 h-3.5" />
            </a>

            <button
              @click="endMeetingEarly(m)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/20 transition cursor-pointer"
              title="End meeting early and immediately free pooled Zoom host license"
            >
              <Square class="w-3.5 h-3.5" />
              <span>End Early & Free License</span>
            </button>

            <a
              v-if="m.join_url"
              :href="m.join_url"
              target="_blank"
              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition"
            >
              <ExternalLink class="w-3.5 h-3.5" />
              <span>Join Live</span>
            </a>
          </div>
        </div>
      </div>
    </GlassCard>

    <!-- Recent Meetings Grid -->
    <GlassCard :title="stats.is_admin ? 'Upcoming & Recent Meetings' : 'My Meeting Requests & Bookings'">
      <template #actions>
        <router-link
          to="/app/meetings"
          class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline"
        >
          View All &rarr;
        </router-link>
      </template>

      <div v-if="loading && recentMeetings.length === 0" class="py-12 text-center text-sm text-slate-400">
        Loading meeting data...
      </div>

      <div v-else-if="recentMeetings.length === 0" class="py-12 text-center">
        <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 mx-auto mb-3">
          <Calendar class="w-6 h-6" />
        </div>
        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ stats.is_admin ? 'No upcoming meetings' : 'No meeting requests found' }}
        </div>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
          {{ stats.is_admin ? 'Reserve your first Zoom meeting resource using our intelligent conflict-free scheduler.' : 'Submit your first Zoom meeting request using the request form.' }}
        </p>
        <router-link
          to="/app/meetings/create"
          class="inline-block mt-4 text-xs font-semibold text-brand-600 dark:text-brand-400"
        >
          {{ stats.is_admin ? '+ Schedule Meeting' : '+ Submit Request' }}
        </router-link>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
              <th class="py-3 px-3">Title</th>
              <th class="py-3 px-3">Start Time</th>
              <th class="py-3 px-3">Status</th>
              <th class="py-3 px-3">Host</th>
              <th class="py-3 px-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
            <tr v-for="meeting in recentMeetings" :key="meeting.public_id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
              <td class="py-3 px-3">
                <div class="font-semibold text-slate-900 dark:text-white">
                  {{ meeting.title }}
                </div>
                <div class="text-xs text-slate-400">
                  ID: {{ meeting.public_id }}
                </div>
              </td>
              <td class="py-3 px-3 text-xs text-slate-600 dark:text-slate-300">
                {{ formatDateTime(meeting.starts_at) }}
              </td>
              <td class="py-3 px-3">
                <span
                  class="glass-badge"
                  :class="{
                    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20': meeting.status === 'started' || meeting.status === 'scheduled',
                    'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20': meeting.status === 'allocating' || meeting.status === 'pending_approval',
                    'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20': meeting.status === 'waitlisted',
                    'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20': meeting.status === 'completed' || meeting.status === 'ended',
                    'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20': meeting.status === 'cancelled' || meeting.status === 'rejected',
                  }"
                >
                  {{ formatStatus(meeting.status) }}
                </span>
              </td>
              <td class="py-3 px-3 text-xs text-slate-600 dark:text-slate-300">
                {{ meeting.owner_name }}
              </td>
              <td class="py-3 px-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <a
                    :href="`/spa/meetings/${meeting.public_id}/ics`"
                    download
                    class="p-1.5 rounded-lg text-slate-500 hover:text-brand-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    title="Download .ICS Calendar"
                  >
                    <Download class="w-3.5 h-3.5" />
                  </a>

                  <button
                    v-if="meeting.status === 'started'"
                    @click="endMeetingEarly(meeting)"
                    class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/60 transition-colors cursor-pointer"
                    title="End Early & Free License"
                  >
                    <Square class="w-3.5 h-3.5" />
                  </button>

                  <a
                    v-if="meeting.join_url"
                    :href="meeting.join_url"
                    target="_blank"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-brand-50 dark:bg-brand-950/60 text-brand-600 dark:text-brand-400 hover:bg-brand-100 text-xs font-semibold transition-colors"
                  >
                    <ExternalLink class="w-3 h-3" />
                    <span>Join</span>
                  </a>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </GlassCard>

    <!-- Resource Pools Overview (Admin Only) -->
    <div v-if="stats.is_admin && pools.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <GlassCard
        v-for="pool in pools"
        :key="pool.id"
        :title="pool.name"
        :subtitle="'Strategy: ' + pool.strategy"
      >
        <div class="flex items-center justify-between mt-2">
          <span class="text-xs text-slate-500">Allocated Host Licenses</span>
          <span class="text-sm font-bold text-slate-900 dark:text-white">
            {{ pool.resources_count }}
          </span>
        </div>
      </GlassCard>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import GlassCard from '@/components/GlassCard.vue';
import {
  Calendar,
  Radio,
  Server,
  Clock,
  CheckCircle2,
  Plus,
  RefreshCw,
  ExternalLink,
  Download,
  Square,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const toast = useToastStore();
const loading = ref(false);

const stats = ref({
  is_admin: true,
  meetings_today: 0,
  active_meetings: 0,
  upcoming_meetings: 0,
  total_pools: 0,
  total_licenses: 0,
  active_licenses: 0,
  pending_approvals: 0,
  my_pending_requests: 0,
  my_total_requests: 0,
  my_approved_meetings: 0,
});

const recentMeetings = ref([]);
const activeMeetingsList = ref([]);
const pools = ref([]);

const formatStatus = (s) => {
  const map = {
    pending_approval: 'Pending Review',
    allocating: 'Allocating',
    waitlisted: 'Waitlisted',
    scheduled: 'Scheduled',
    started: 'Live Now',
    ended: 'Ended',
    completed: 'Completed',
    cancelled: 'Cancelled',
    rejected: 'Rejected',
  };
  return map[s] || s;
};

const formatDateTime = (dateStr) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const fetchStats = async () => {
  try {
    loading.value = true;
    const response = await axios.get('/spa/dashboard/stats');
    stats.value = response.data.stats;
    recentMeetings.value = response.data.recent_meetings || [];
    activeMeetingsList.value = response.data.active_meetings_list || [];
    pools.value = response.data.pools || [];
  } catch (error) {
    console.error('Failed to load dashboard data', error);
  } finally {
    loading.value = false;
  }
};

const endMeetingEarly = async (meeting) => {
  if (!confirm(`Are you sure you want to end "${meeting.title}" early? This will immediately free the pooled Zoom host license.`)) {
    return;
  }

  try {
    const res = await axios.post(`/spa/meetings/${meeting.public_id}/end-early`);
    if (res.data.success) {
      toast.success(res.data.message);
      await fetchStats();
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to end meeting early.');
  }
};

onMounted(() => {
  fetchStats();
});
</script>
