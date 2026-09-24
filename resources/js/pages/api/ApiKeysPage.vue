<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          API Keys & Access Tokens
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Programmatic credentials for REST API v1 integration, webhooks, and headless automation
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchKeys"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          @click="showCreateModal = true"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Key class="w-4 h-4" />
          <span>Generate API Key</span>
        </button>
      </div>
    </div>

    <!-- Alert / Feedback -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Plain-Text Token Notification Banner -->
    <div v-if="newPlainToken" class="p-5 rounded-2xl bg-amber-500/10 border border-amber-500/30 space-y-2">
      <div class="flex items-center gap-2 text-amber-800 dark:text-amber-200 text-xs font-bold">
        <Key class="w-4 h-4 text-amber-500" />
        <span>New API Token Generated — Copy Now</span>
      </div>
      <p class="text-xs text-amber-700 dark:text-amber-300">
        This secret token will <strong>never be shown again</strong>. Please copy and store it securely in your environment variables or secrets manager.
      </p>
      <div class="flex items-center gap-2 pt-1">
        <input
          readonly
          :value="newPlainToken"
          class="flex-1 font-mono text-xs px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-700 text-slate-900 dark:text-white select-all"
        />
        <button
          @click="copyToken"
          class="px-4 py-2 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-600 text-white transition flex items-center gap-1.5 shadow-sm"
        >
          <Copy class="w-3.5 h-3.5" />
          <span>{{ copied ? 'Copied!' : 'Copy' }}</span>
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
          placeholder="Search keys by name, prefix, scope, or creator..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Keys Table -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div v-if="loading && !keys.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Loading API tokens...</p>
      </div>

      <div v-else-if="!filteredKeys.length" class="p-12 text-center text-slate-400">
        <Key class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No API Keys Match Search' : 'No API Keys Generated' }}
        </p>
        <p class="text-xs text-slate-400 mt-1">
          {{ searchQuery ? 'Try adjusting your search query.' : 'Create an API key to enable external service integrations.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Key Name</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Token Prefix</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Scopes</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Rate Limit</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Expires</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr
              v-for="key in filteredKeys"
              :key="key.public_id || key.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
            >
              <td class="py-3.5 px-4">
                <div class="font-bold text-xs text-slate-900 dark:text-white">{{ key.name }}</div>
                <div class="text-[11px] text-slate-400">Created by: {{ key.user?.name || 'Admin' }}</div>
              </td>
              <td class="py-3.5 px-4">
                <code class="px-2 py-0.5 rounded font-mono text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                  {{ key.token_prefix }}...
                </code>
              </td>
              <td class="py-3.5 px-4">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="scope in key.scopes"
                    :key="scope"
                    class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300"
                  >
                    {{ scope }}
                  </span>
                </div>
              </td>
              <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-400">
                {{ key.rate_limit || 60 }} req/min
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="key.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400'"
                >
                  {{ key.is_active ? 'Active' : 'Revoked' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-xs text-slate-500 dark:text-slate-400">
                {{ key.expires_at ? formatDate(key.expires_at) : 'Never' }}
              </td>
              <td class="py-3.5 px-4 text-right">
                <button
                  v-if="key.is_active"
                  @click="revokeKey(key)"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-rose-200 dark:border-rose-900 transition"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                  <span>Revoke</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create API Key Modal -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">Generate New API Key</h2>
          <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Key Name / Identifier *</label>
            <input
              v-model="createForm.name"
              type="text"
              placeholder="e.g. Canvas LMS Integration Token"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Permitted Scopes *</label>
            <div class="grid grid-cols-2 gap-2 text-xs">
              <label
                v-for="s in availableScopes"
                :key="s.value"
                class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer"
              >
                <input
                  type="checkbox"
                  :value="s.value"
                  v-model="createForm.scopes"
                  class="rounded text-brand-600 focus:ring-brand-500"
                />
                <span class="text-slate-800 dark:text-slate-200">{{ s.label }}</span>
              </label>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Rate Limit (req/min)</label>
              <input
                v-model.number="createForm.rate_limit"
                type="number"
                min="10"
                max="1000"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Expiration (Days)</label>
              <select
                v-model="createForm.expires_days"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              >
                <option :value="null">Never expires</option>
                <option :value="30">30 Days</option>
                <option :value="90">90 Days</option>
                <option :value="180">180 Days</option>
                <option :value="365">1 Year</option>
              </select>
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
            @click="submitCreateKey"
            :disabled="creating || !createForm.name || !createForm.scopes.length"
            class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
          >
            {{ creating ? 'Generating...' : 'Generate Key' }}
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
  Key,
  Copy,
  Trash2,
  RefreshCw,
  Search,
  X,
} from 'lucide-vue-next';

const keys = ref([]);
const loading = ref(false);
const feedback = ref('');
const searchQuery = ref('');

const filteredKeys = computed(() => {
  if (!searchQuery.value.trim()) return keys.value;
  const q = searchQuery.value.toLowerCase().trim();
  return keys.value.filter((k) => {
    const scopesStr = (k.scopes || []).join(' ');
    return (
      (k.name && k.name.toLowerCase().includes(q)) ||
      (k.token_prefix && k.token_prefix.toLowerCase().includes(q)) ||
      (k.user?.name && k.user.name.toLowerCase().includes(q)) ||
      scopesStr.toLowerCase().includes(q)
    );
  });
});

const showCreateModal = ref(false);
const creating = ref(false);
const newPlainToken = ref('');
const copied = ref(false);

const availableScopes = [
  { value: 'meetings:read', label: 'meetings:read' },
  { value: 'meetings:write', label: 'meetings:write' },
  { value: 'recordings:read', label: 'recordings:read' },
  { value: 'quotas:read', label: 'quotas:read' },
  { value: 'webhooks:manage', label: 'webhooks:manage' },
  { value: 'system:read', label: 'system:read' },
];

const createForm = ref({
  name: '',
  scopes: ['meetings:read', 'meetings:write'],
  rate_limit: 60,
  expires_days: 90,
});

const fetchKeys = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/admin/api-keys', {
      headers: { Accept: 'application/json' },
    });
    keys.value = res.data?.data || res.data || [];
  } catch (err) {
    console.error('Failed to load API keys', err);
  } finally {
    loading.value = false;
  }
};

const submitCreateKey = async () => {
  creating.value = true;
  feedback.value = '';
  try {
    const res = await axios.post('/admin/api-keys', createForm.value, {
      headers: { Accept: 'application/json' },
    });
    newPlainToken.value = res.data?.plainTextToken || '';
    copied.value = false;
    showCreateModal.value = false;
    feedback.value = 'API key created successfully.';
    createForm.value = {
      name: '',
      scopes: ['meetings:read', 'meetings:write'],
      rate_limit: 60,
      expires_days: 90,
    };
    await fetchKeys();
  } catch (err) {
    console.error('Failed to create key', err);
  } finally {
    creating.value = false;
  }
};

const revokeKey = async (key) => {
  if (!confirm(`Are you sure you want to revoke API key "${key.name}"?`)) return;
  try {
    await axios.post(`/admin/api-keys/${key.public_id}/revoke`, {}, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = `API key "${key.name}" revoked.`;
    await fetchKeys();
  } catch (err) {
    console.error('Failed to revoke API key', err);
  }
};

const copyToken = () => {
  navigator.clipboard.writeText(newPlainToken.value);
  copied.value = true;
  setTimeout(() => (copied.value = false), 2500);
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleDateString();
};

onMounted(fetchKeys);
</script>
