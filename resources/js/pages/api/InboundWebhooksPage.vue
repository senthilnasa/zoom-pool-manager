<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Zoom Webhook Intake Logs
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Real-time intake stream of Zoom event notifications, HMAC signature validation, and event replay
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <div class="relative w-48 sm:w-64">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search event type, ID, IP..."
            class="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
          />
        </div>

        <select
          v-model="filterStatus"
          @change="fetchEvents"
          class="text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
        >
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="processed">Processed</option>
          <option value="failed">Failed</option>
        </select>

        <button
          @click="fetchEvents"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>
      </div>
    </div>

    <!-- Alert / Feedback -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Events List Card -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div v-if="loading && !events.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Listening to intake stream...</p>
      </div>

      <div v-else-if="!filteredEvents.length" class="p-12 text-center text-slate-400">
        <Radio class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No Matching Inbound Events' : 'No Inbound Events Received' }}
        </p>
        <p class="text-xs text-slate-400 mt-1">
          {{ searchQuery ? 'Try adjusting your search query.' : 'Configure your Zoom App webhook endpoint to point to this server to receive live events.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Event Type</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Event ID</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Signature</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">IP Address</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Received At</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr
              v-for="ev in filteredEvents"
              :key="ev.public_id || ev.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
            >
              <td class="py-3.5 px-4 font-mono font-bold text-xs text-slate-900 dark:text-white">
                {{ ev.event_type }}
              </td>
              <td class="py-3.5 px-4 font-mono text-[11px] text-slate-500 dark:text-slate-400 max-w-[140px] truncate" :title="ev.event_id">
                {{ ev.event_id }}
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="getStatusBadgeClass(ev.status)"
                >
                  {{ ev.status }}
                </span>
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="inline-flex items-center gap-1 text-[11px] font-semibold"
                  :class="ev.signature_valid ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                >
                  <CheckCircle2 v-if="ev.signature_valid" class="w-3.5 h-3.5" />
                  <AlertCircle v-else class="w-3.5 h-3.5" />
                  {{ ev.signature_valid ? 'Valid HMAC' : 'Invalid' }}
                </span>
              </td>
              <td class="py-3.5 px-4 font-mono text-xs text-slate-500 dark:text-slate-400">
                {{ ev.ip_address || '—' }}
              </td>
              <td class="py-3.5 px-4 text-xs text-slate-500 dark:text-slate-400">
                {{ formatDate(ev.created_at) }}
              </td>
              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    @click="viewPayload(ev)"
                    class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    title="View JSON Payload"
                  >
                    <Eye class="w-4 h-4" />
                  </button>
                  <button
                    @click="replayEvent(ev)"
                    :disabled="replayingId === ev.public_id"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition"
                    title="Replay Event"
                  >
                    <RotateCcw class="w-3.5 h-3.5 text-brand-500" :class="{ 'animate-spin': replayingId === ev.public_id }" />
                    <span>Replay</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Payload Modal -->
    <div
      v-if="payloadModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <div class="text-sm font-bold text-slate-900 dark:text-white font-mono">
            {{ selectedEvent?.event_type }}
          </div>
          <button @click="payloadModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between text-xs text-slate-400 font-mono">
            <span>Event ID: {{ selectedEvent?.event_id }}</span>
            <span>Received: {{ formatDate(selectedEvent?.created_at) }}</span>
          </div>

          <pre class="w-full p-4 rounded-xl bg-slate-900 text-slate-200 text-xs font-mono overflow-x-auto max-h-96 leading-relaxed">{{ JSON.stringify(selectedEvent?.payload, null, 2) }}</pre>
        </div>

        <div class="flex items-center justify-end pt-2 border-t border-slate-200/60 dark:border-slate-800/60">
          <button
            @click="payloadModal = false"
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
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import {
  Radio,
  Eye,
  RotateCcw,
  CheckCircle2,
  AlertCircle,
  RefreshCw,
  Search,
  X,
} from 'lucide-vue-next';

const events = ref([]);
const loading = ref(false);
const filterStatus = ref('');
const feedback = ref('');
const replayingId = ref(null);
const searchQuery = ref('');

const filteredEvents = computed(() => {
  if (!searchQuery.value.trim()) return events.value;
  const q = searchQuery.value.toLowerCase().trim();
  return events.value.filter((ev) => {
    return (
      (ev.event_type && ev.event_type.toLowerCase().includes(q)) ||
      (ev.event_id && ev.event_id.toLowerCase().includes(q)) ||
      (ev.ip_address && ev.ip_address.toLowerCase().includes(q)) ||
      (ev.status && ev.status.toLowerCase().includes(q))
    );
  });
});

const payloadModal = ref(false);
const selectedEvent = ref(null);

const fetchEvents = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/admin/webhooks', {
      params: { status: filterStatus.value },
      headers: { Accept: 'application/json' },
    });
    events.value = res.data?.events?.data || res.data?.events || [];
  } catch (err) {
    console.error('Failed to load webhook events', err);
  } finally {
    loading.value = false;
  }
};

const viewPayload = (ev) => {
  selectedEvent.value = ev;
  payloadModal.value = true;
};

const replayEvent = async (ev) => {
  replayingId.value = ev.public_id;
  try {
    const res = await axios.post(`/admin/webhooks/${ev.public_id}/replay`, {}, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = res.data?.message || 'Event queued for replay.';
    await fetchEvents();
  } catch (err) {
    console.error('Failed to replay webhook event', err);
  } finally {
    replayingId.value = null;
  }
};

const getStatusBadgeClass = (status) => {
  if (status === 'processed') return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
  if (status === 'failed') return 'bg-rose-500/10 text-rose-600 dark:text-rose-400';
  return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleString();
};

onMounted(fetchEvents);
</script>
