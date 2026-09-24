<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Scheduling & Booking Policies
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Advance booking constraints, minimum lead time notice, session buffers, and duration caps
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchPolicies"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          v-if="authStore.isAdmin"
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Plus class="w-4 h-4" />
          <span>New Policy</span>
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
          placeholder="Search policies by name or department..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Alert / Feedback -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Policies Grid -->
    <div v-if="loading && !policies.length" class="p-12 text-center text-slate-400">
      <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
      <p class="text-sm">Loading booking policies...</p>
    </div>

    <div v-else-if="!filteredPolicies.length" class="p-12 text-center text-slate-400 glass-card rounded-2xl border border-slate-200/60 dark:border-slate-800/60">
      <Clock class="w-10 h-10 mx-auto mb-3 opacity-40" />
      <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
        {{ searchQuery ? 'No Matching Policies' : 'No Policies Found' }}
      </p>
      <p class="text-xs text-slate-400 mt-1">
        {{ searchQuery ? 'Try adjusting your search criteria.' : 'Create policies to govern advance booking horizons, notice hours, and buffer times.' }}
      </p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="pol in filteredPolicies"
        :key="pol.id"
        class="glass-card rounded-2xl p-5 border border-slate-200/60 dark:border-slate-800/60 space-y-4 hover:border-brand-500/40 transition flex flex-col justify-between"
      >
        <div class="space-y-3">
          <div class="flex items-start justify-between gap-2">
            <div>
              <h3 class="font-bold text-sm text-slate-900 dark:text-white">{{ pol.name }}</h3>
              <span class="text-xs text-brand-600 dark:text-brand-400 font-medium">
                {{ pol.department?.name || 'Global Default Policy' }}
              </span>
            </div>
            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
              :class="pol.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-500'"
            >
              {{ pol.is_active ? 'Active' : 'Disabled' }}
            </span>
          </div>

          <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
              <span class="text-slate-400">Min Notice (Lead Time):</span>
              <span class="font-semibold">{{ pol.min_notice_hours }} hour(s)</span>
            </div>
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
              <span class="text-slate-400">Max Advance Booking:</span>
              <span class="font-semibold">{{ pol.max_advance_days }} day(s)</span>
            </div>
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
              <span class="text-slate-400">Inter-Meeting Buffer:</span>
              <span class="font-semibold">{{ pol.default_buffer_minutes }} mins (min: {{ pol.min_buffer_minutes }}m)</span>
            </div>
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
              <span class="text-slate-400">Max Duration:</span>
              <span class="font-semibold">{{ pol.max_duration_minutes }} mins</span>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <button
            v-if="authStore.isAdmin"
            @click="editPolicy(pol)"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            Edit Policy
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div
      v-if="modalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ editingItem ? 'Edit Booking Policy' : 'New Booking Policy' }}</h2>
          <button @click="modalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="savePolicy" class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Policy Title *</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Applicable Department Scope</label>
            <select
              v-model="form.department_id"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option :value="null">Global Default (All Departments)</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Min Notice (Hours)</label>
              <input
                v-model.number="form.min_notice_hours"
                type="number"
                min="0"
                max="168"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Max Advance (Days)</label>
              <input
                v-model.number="form.max_advance_days"
                type="number"
                min="1"
                max="365"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Buffer Between (Mins)</label>
              <input
                v-model.number="form.default_buffer_minutes"
                type="number"
                min="0"
                max="60"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Max Duration (Mins)</label>
              <input
                v-model.number="form.max_duration_minutes"
                type="number"
                min="15"
                max="720"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
          </div>

          <div class="pt-2">
            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.is_active"
                class="rounded text-brand-600 focus:ring-brand-500"
              />
              <span>Policy Enforced</span>
            </label>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
            <button
              type="button"
              @click="modalOpen = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save Policy' }}
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
  Clock,
  Plus,
  RefreshCw,
  Search,
  X,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const policies = ref([]);
const departments = ref([]);
const loading = ref(false);
const saving = ref(false);
const feedback = ref('');
const searchQuery = ref('');

const filteredPolicies = computed(() => {
  if (!searchQuery.value.trim()) return policies.value;
  const q = searchQuery.value.toLowerCase().trim();
  return policies.value.filter((p) => {
    return (
      (p.name && p.name.toLowerCase().includes(q)) ||
      (p.department?.name && p.department.name.toLowerCase().includes(q))
    );
  });
});

const modalOpen = ref(false);
const editingItem = ref(null);
const form = ref({
  id: null,
  name: '',
  department_id: null,
  min_notice_hours: 2,
  max_advance_days: 60,
  min_buffer_minutes: 5,
  default_buffer_minutes: 15,
  max_duration_minutes: 240,
  is_active: true,
});

const fetchPolicies = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/spa/policies');
    policies.value = res.data?.policies || [];
    departments.value = res.data?.departments || [];
  } catch (err) {
    console.error('Failed to load policies', err);
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  editingItem.value = null;
  form.value = {
    id: null,
    name: '',
    department_id: null,
    min_notice_hours: 2,
    max_advance_days: 60,
    min_buffer_minutes: 5,
    default_buffer_minutes: 15,
    max_duration_minutes: 240,
    is_active: true,
  };
  modalOpen = true;
};

const editPolicy = (pol) => {
  editingItem.value = pol;
  form.value = {
    id: pol.id,
    name: pol.name,
    department_id: pol.department_id,
    min_notice_hours: pol.min_notice_hours,
    max_advance_days: pol.max_advance_days,
    min_buffer_minutes: pol.min_buffer_minutes,
    default_buffer_minutes: pol.default_buffer_minutes,
    max_duration_minutes: pol.max_duration_minutes,
    is_active: !!pol.is_active,
  };
  modalOpen = true;
};

const savePolicy = async () => {
  saving.value = true;
  try {
    await axios.post('/spa/policies', form.value);
    feedback.value = `Policy "${form.value.name}" saved successfully.`;
    modalOpen = false;
    await fetchPolicies();
  } catch (err) {
    console.error('Failed to save policy', err);
  } finally {
    saving.value = false;
  }
};

onMounted(fetchPolicies);
</script>
