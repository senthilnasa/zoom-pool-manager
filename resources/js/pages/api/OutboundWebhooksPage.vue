<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Outbound Webhooks
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Push event notifications to external webhooks on meeting, recording, and quota lifecycle events
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchWebhooks"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          @click="showCreateModal = true"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Webhook class="w-4 h-4" />
          <span>New Subscription</span>
        </button>
      </div>
    </div>

    <!-- Feedback Message -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Subscriptions Section -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div class="px-6 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">Active Subscriptions</h2>
          <span class="text-xs text-slate-400 font-medium">({{ subscriptions.length }})</span>
        </div>
        <div class="relative w-full sm:w-64">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search subscriptions or URLs..."
            class="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
          />
        </div>
      </div>

      <div v-if="loading && !subscriptions.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Loading webhook subscriptions...</p>
      </div>

      <div v-else-if="!filteredSubscriptions.length" class="p-12 text-center text-slate-400">
        <Webhook class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No Webhook Subscriptions Match Search' : 'No Webhook Subscriptions' }}
        </p>
        <p class="text-xs text-slate-400 mt-1">
          {{ searchQuery ? 'Try adjusting your search query.' : 'Register an endpoint URL to start receiving event notifications.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Subscription Name</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Endpoint URL</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Subscribed Events</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr
              v-for="sub in filteredSubscriptions"
              :key="sub.public_id || sub.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
            >
              <td class="py-3.5 px-4">
                <div class="font-bold text-xs text-slate-900 dark:text-white">{{ sub.name }}</div>
                <div class="text-[11px] text-slate-400">By: {{ sub.creator?.name || 'Admin' }}</div>
              </td>
              <td class="py-3.5 px-4">
                <div class="font-mono text-xs text-slate-700 dark:text-slate-300 max-w-xs truncate" :title="sub.url">
                  {{ sub.url }}
                </div>
              </td>
              <td class="py-3.5 px-4">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="ev in sub.events"
                    :key="ev"
                    class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300"
                  >
                    {{ ev }}
                  </span>
                </div>
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="sub.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-500'"
                >
                  {{ sub.is_active ? 'Active' : 'Disabled' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button
                    @click="testPing(sub)"
                    :disabled="testingId === sub.public_id"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition"
                  >
                    <Send class="w-3.5 h-3.5 text-brand-500" />
                    <span>{{ testingId === sub.public_id ? 'Pinging...' : 'Test Ping' }}</span>
                  </button>
                  <button
                    @click="toggleActive(sub)"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold"
                    :class="sub.is_active ? 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40' : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'"
                  >
                    <span>{{ sub.is_active ? 'Disable' : 'Enable' }}</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Deliveries Section -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div class="px-6 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Recent Outbound Deliveries Log</h2>
        <span class="text-xs text-slate-400 font-medium">Last 25 attempts</span>
      </div>

      <div v-if="!deliveries.length" class="p-8 text-center text-slate-400 text-xs">
        No outbound delivery logs recorded yet.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Event</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Target Subscriber</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status Code</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Attempts</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Dispatched At</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
            <tr
              v-for="del in deliveries"
              :key="del.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
            >
              <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                {{ del.event }}
              </td>
              <td class="py-3 px-4 text-slate-600 dark:text-slate-400 truncate max-w-xs">
                {{ del.subscription?.name || del.subscription?.url || 'Subscriber' }}
              </td>
              <td class="py-3 px-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
                  :class="del.response_status >= 200 && del.response_status < 300 ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400'"
                >
                  {{ del.response_status || 'ERR' }}
                </span>
              </td>
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                {{ del.attempts || 1 }} attempt(s)
              </td>
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400 text-right">
                {{ formatDate(del.created_at) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Subscription Modal -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">New Outbound Webhook Subscription</h2>
          <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Subscription Name *</label>
            <input
              v-model="createForm.name"
              type="text"
              placeholder="e.g. ERP Attendance Sync"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Target Webhook URL *</label>
            <input
              v-model="createForm.url"
              type="url"
              placeholder="https://api.yourdomain.com/webhooks/zoom-pool"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">HMAC Secret (min 16 chars) *</label>
            <input
              v-model="createForm.secret"
              type="text"
              placeholder="Enter a cryptographically random secret"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Events to Subscribe *</label>
            <div class="grid grid-cols-2 gap-2 text-xs">
              <label
                v-for="ev in availableEvents"
                :key="ev"
                class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer"
              >
                <input
                  type="checkbox"
                  :value="ev"
                  v-model="createForm.events"
                  class="rounded text-brand-600 focus:ring-brand-500"
                />
                <span class="text-slate-800 dark:text-slate-200 font-mono text-[11px]">{{ ev }}</span>
              </label>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
          <button
            @click="showCreateModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            Cancel
          </button>
          <button
            @click="submitCreate"
            :disabled="creating || !createForm.name || !createForm.url || !createForm.secret || !createForm.events.length"
            class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
          >
            {{ creating ? 'Subscribing...' : 'Register Subscription' }}
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
  Webhook,
  Send,
  RefreshCw,
  Search,
  X,
} from 'lucide-vue-next';

const subscriptions = ref([]);
const deliveries = ref([]);
const loading = ref(false);
const feedback = ref('');
const testingId = ref(null);
const searchQuery = ref('');

const filteredSubscriptions = computed(() => {
  if (!searchQuery.value.trim()) return subscriptions.value;
  const q = searchQuery.value.toLowerCase().trim();
  return subscriptions.value.filter((s) => {
    const eventsStr = (s.events || []).join(' ');
    return (
      (s.name && s.name.toLowerCase().includes(q)) ||
      (s.url && s.url.toLowerCase().includes(q)) ||
      (s.creator?.name && s.creator.name.toLowerCase().includes(q)) ||
      eventsStr.toLowerCase().includes(q)
    );
  });
});

const showCreateModal = ref(false);
const creating = ref(false);

const availableEvents = [
  'meeting.created',
  'meeting.started',
  'meeting.ended',
  'meeting.cancelled',
  'recording.completed',
  'approval.requested',
  'quota.exceeded',
];

const createForm = ref({
  name: '',
  url: '',
  secret: 'whsec_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15),
  events: ['meeting.created', 'meeting.ended'],
});

const fetchWebhooks = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/admin/outbound-webhooks', {
      headers: { Accept: 'application/json' },
    });
    subscriptions.value = res.data?.subscriptions || [];
    deliveries.value = res.data?.deliveries || [];
  } catch (err) {
    console.error('Failed to load outbound webhooks', err);
  } finally {
    loading.value = false;
  }
};

const submitCreate = async () => {
  creating.value = true;
  feedback.value = '';
  try {
    await axios.post('/admin/outbound-webhooks', createForm.value, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = 'Webhook subscription registered successfully.';
    showCreateModal.value = false;
    createForm.value = {
      name: '',
      url: '',
      secret: 'whsec_' + Math.random().toString(36).substring(2, 15),
      events: ['meeting.created', 'meeting.ended'],
    };
    await fetchWebhooks();
  } catch (err) {
    console.error('Failed to create subscription', err);
  } finally {
    creating.value = false;
  }
};

const testPing = async (sub) => {
  testingId.value = sub.public_id;
  try {
    const res = await axios.post(`/admin/outbound-webhooks/${sub.public_id}/test`, {}, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = res.data?.message || 'Test ping dispatched.';
    await fetchWebhooks();
  } catch (err) {
    console.error('Failed to dispatch test ping', err);
  } finally {
    testingId.value = null;
  }
};

const toggleActive = async (sub) => {
  try {
    await axios.post(`/admin/outbound-webhooks/${sub.public_id}/toggle`, {}, {
      headers: { Accept: 'application/json' },
    });
    await fetchWebhooks();
  } catch (err) {
    console.error('Failed to toggle webhook', err);
  }
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleString();
};

onMounted(fetchWebhooks);
</script>
