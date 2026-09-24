<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Quotas & Limits Management
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Enforce monthly meeting count and pooled hours limits by User and Department
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          v-if="authStore.can('quota.manage') || authStore.isAdmin"
          @click="showModal = true"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Plus class="w-4 h-4" />
          <span>Configure Quota</span>
        </button>
      </div>
    </div>

    <!-- Active Period & Search Card -->
    <div class="glass-card p-4 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4 border border-slate-200/60 dark:border-slate-800/60">
      <div class="flex items-center gap-3">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Enforcement Period:</span>
        <span class="px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200">
          {{ currentPeriod || 'Current Month' }}
        </span>
      </div>
      <div class="relative w-full sm:w-72">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by target or scope..."
          class="w-full pl-10 pr-4 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Quotas Table -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div v-if="loading && !quotas.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Loading quota policies and usage meters...</p>
      </div>

      <div v-else-if="!filteredQuotas.length" class="p-12 text-center text-slate-400">
        <PieChart class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-medium">
          {{ searchQuery ? 'No quotas matching search criteria.' : 'No quotas configured. Unlimited access enabled.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Scope Target</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Meetings Limit / Used</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Hours Limit / Used</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right w-20">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <tr v-for="q in filteredQuotas" :key="q.model.id" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
              <td class="py-4 px-4 font-bold text-slate-900 dark:text-white">
                <div class="flex items-center gap-2">
                  <span
                    class="px-2 py-0.5 rounded-lg text-[10px] font-bold uppercase tracking-wider"
                    :class="q.model.scope_type === 'department' ? 'bg-indigo-500/10 text-indigo-500' : 'bg-sky-500/10 text-sky-500'"
                  >
                    {{ q.model.scope_type }}
                  </span>
                  <span>{{ q.target_name }}</span>
                </div>
              </td>
              <td class="py-4 px-4 text-xs">
                <div v-if="q.model.max_meetings_per_month" class="space-y-1">
                  <div class="flex justify-between text-[11px] font-medium">
                    <span>{{ q.stats?.meetings_used || 0 }} / {{ q.model.max_meetings_per_month }}</span>
                    <span>{{ Math.round(((q.stats?.meetings_used || 0) / q.model.max_meetings_per_month) * 100) }}%</span>
                  </div>
                  <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden">
                    <div
                      class="h-full bg-brand-500 rounded-full"
                      :style="{ width: `${Math.min(100, Math.round(((q.stats?.meetings_used || 0) / q.model.max_meetings_per_month) * 100))}%` }"
                    ></div>
                  </div>
                </div>
                <span v-else class="text-slate-400 italic">Unlimited</span>
              </td>
              <td class="py-4 px-4 text-xs">
                <div v-if="q.model.max_hours_per_month" class="space-y-1">
                  <div class="flex justify-between text-[11px] font-medium">
                    <span>{{ Math.round((q.stats?.minutes_used || 0) / 60) }}h / {{ q.model.max_hours_per_month }}h</span>
                    <span>{{ Math.round(((q.stats?.minutes_used || 0) / (q.model.max_hours_per_month * 60)) * 100) }}%</span>
                  </div>
                  <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden">
                    <div
                      class="h-full bg-indigo-500 rounded-full"
                      :style="{ width: `${Math.min(100, Math.round(((q.stats?.minutes_used || 0) / (q.model.max_hours_per_month * 60)) * 100))}%` }"
                    ></div>
                  </div>
                </div>
                <span v-else class="text-slate-400 italic">Unlimited</span>
              </td>
              <td class="py-4 px-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[11px] font-bold"
                  :class="q.model.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-500'"
                >
                  {{ q.model.is_active ? 'Active' : 'Disabled' }}
                </span>
              </td>
              <td class="py-4 px-4 text-right">
                <button
                  v-if="authStore.can('quota.manage') || authStore.isAdmin"
                  @click="deleteQuota(q.model)"
                  class="p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition"
                  title="Remove Quota Limit"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Configure Quota Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
      @click.self="showModal = false"
    >
      <div class="glass-card max-w-md w-full p-6 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-2xl bg-white dark:bg-slate-900 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <h3 class="font-bold text-base text-slate-900 dark:text-white">Configure Quota Limit</h3>
          <button @click="showModal = false" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitQuota" class="space-y-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Scope Type</label>
            <select
              v-model="form.scope_type"
              class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
            >
              <option value="department">Department</option>
              <option value="user">User</option>
            </select>
          </div>

          <div v-if="form.scope_type === 'department'">
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Select Department</label>
            <SearchableSelect
              v-model="form.scope_id"
              :options="departments"
              placeholder="Search department..."
              search-placeholder="Type department name..."
              label-key="name"
              value-key="id"
            />
          </div>

          <div v-else>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Select User (10,000+ Employees)</label>
            <SearchableSelect
              v-model="form.scope_id"
              remote-url="/spa/users/search"
              :initial-options="users"
              placeholder="Search employee by name or email..."
              search-placeholder="Type name, email, or designation..."
              label-key="name"
              value-key="id"
              sublabel-key="email"
              badge-key="department.name"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Max Meetings / Month</label>
            <input
              v-model.number="form.max_meetings_per_month"
              type="number"
              placeholder="e.g., 20 (leave blank for unlimited)"
              class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Max Pooled Hours / Month</label>
            <input
              v-model.number="form.max_hours_per_month"
              type="number"
              placeholder="e.g., 40 (leave blank for unlimited)"
              class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
            />
          </div>

          <div class="pt-3 flex justify-end gap-2">
            <button
              type="button"
              @click="showModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white transition"
            >
              Save Quota
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
import SearchableSelect from '@/components/SearchableSelect.vue';
import { useAuthStore } from '@/stores/auth';
import {
  PieChart,
  Plus,
  RefreshCw,
  Search,
  Trash2,
  X,
} from 'lucide-vue-next';

const authStore = useAuthStore();

const loading = ref(false);
const saving = ref(false);
const quotas = ref([]);
const departments = ref([]);
const users = ref([]);
const currentPeriod = ref('');
const showModal = ref(false);
const searchQuery = ref('');

const filteredQuotas = computed(() => {
  if (!searchQuery.value.trim()) return quotas.value;
  const q = searchQuery.value.toLowerCase().trim();
  return quotas.value.filter((item) => {
    return (
      (item.target_name && item.target_name.toLowerCase().includes(q)) ||
      (item.model?.scope_type && item.model.scope_type.toLowerCase().includes(q))
    );
  });
});

const form = ref({
  scope_type: 'department',
  scope_id: null,
  max_meetings_per_month: null,
  max_hours_per_month: null,
  is_active: true,
});

const fetchQuotas = async () => {
  try {
    loading.value = true;
    const res = await axios.get('/admin/quotas');
    quotas.value = res.data.quotas?.data || res.data.quotas || [];
    departments.value = res.data.departments || [];
    users.value = res.data.users || [];
    currentPeriod.value = res.data.current_period || '';
  } catch (err) {
    console.error('Failed to load quotas', err);
  } finally {
    loading.value = false;
  }
};

const submitQuota = async () => {
  try {
    saving.value = true;
    await axios.post('/admin/quotas', form.value);
    showModal.value = false;
    await fetchQuotas();
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to configure quota.');
  } finally {
    saving.value = false;
  }
};

const deleteQuota = async (quota) => {
  if (!confirm('Remove this quota limit?')) return;
  try {
    await axios.delete(`/admin/quotas/${quota.public_id}`);
    await fetchQuotas();
  } catch (err) {
    alert('Failed to remove quota.');
  }
};

onMounted(() => {
  fetchQuotas();
});
</script>
